#!/usr/bin/env python3
"""cdp.py : pilote Chrome headless par le protocole DevTools (CDP), bibliothèque standard seulement.

Node n'est pas installé sur le poste : ce module remplace les harnais .mjs. Il lance Chrome
(rendu WebGL par Metal), ouvre une page et expose de quoi tester : evaluer du JavaScript,
defiler, cliquer, capturer, relever les erreurs de console.

    from cdp import Chrome
    with Chrome(taille=(1440, 900)) as c:
        c.ouvrir(adresse_site())
        c.attendre(2)
        print(c.js('document.title'))
        c.capture('/tmp/accueil.png')
        print(c.erreurs)

En ligne de commande :
    python3 outils/cdp.py URL sortie.png [--taille 1440x900] [--attente 2] [--defile 1200] [--js "expr"]
"""
import base64, json, os, shutil, socket, struct, subprocess, sys, tempfile, time, urllib.request

CHROME = os.environ.get('CHROME') or '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
RACINE_SITE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def _env_pupitre():
    """Les variables de pupitre/.env (CLE=valeur, # pour un commentaire), sans les afficher."""
    v = {}
    try:
        with open(os.path.join(RACINE_SITE, 'pupitre', '.env'), encoding='utf-8') as f:
            for ligne in f:
                ligne = ligne.strip()
                if not ligne or ligne[0] == '#' or '=' not in ligne:
                    continue
                cle, _, valeur = ligne.partition('=')
                valeur = valeur.strip()
                if len(valeur) >= 2 and valeur[0] in '"\'' and valeur[-1] == valeur[0]:
                    valeur = valeur[1:-1]
                v[cle.strip()] = valeur
    except OSError:
        pass
    return v


def adresse_site():
    """Adresse du site, avec barre finale : SITE_URL, sinon le dossier parent de APP_URL (pupitre/.env)."""
    url = os.environ.get('SITE_URL') or ''
    if not url:
        app = _env_pupitre().get('APP_URL', '').rstrip('/')
        if not app:
            raise SystemExit('Adresse du site inconnue : renseignez APP_URL dans pupitre/.env, ou SITE_URL.')
        url = app.rsplit('/', 1)[0]
    return url.rstrip('/') + '/'


def php_cli():
    """PHP en ligne de commande : PHP_CLI (environnement, puis pupitre/.env), sinon php du PATH."""
    p = os.environ.get('PHP_CLI') or _env_pupitre().get('PHP_CLI') or shutil.which('php')
    if not p:
        raise SystemExit('PHP introuvable : renseignez PHP_CLI dans pupitre/.env.')
    return p


class WS:
    """Client WebSocket minimal (RFC 6455) : texte, trames fragmentées, ping."""
    def __init__(self, url):
        assert url.startswith('ws://')
        hote, _, chemin = url[5:].partition('/')
        h, _, p = hote.partition(':')
        self.s = socket.create_connection((h, int(p or 80)))
        cle = base64.b64encode(os.urandom(16)).decode()
        self.s.sendall(('GET /%s HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n'
                        'Sec-WebSocket-Key: %s\r\nSec-WebSocket-Version: 13\r\n\r\n' % (chemin, hote, cle)).encode())
        tampon = b''
        while b'\r\n\r\n' not in tampon:
            tampon += self.s.recv(4096)
        if b' 101 ' not in tampon.split(b'\r\n')[0]:
            raise RuntimeError('WebSocket refusé : %r' % tampon[:120])
        self.reste = tampon.split(b'\r\n\r\n', 1)[1]

    def _lire(self, n):
        while len(self.reste) < n:
            d = self.s.recv(65536)
            if not d:
                raise ConnectionError('WebSocket fermé')
            self.reste += d
        d, self.reste = self.reste[:n], self.reste[n:]
        return d

    def envoyer(self, texte):
        d = texte.encode()
        n = len(d)
        tete = b'\x81'
        if n < 126: tete += bytes([0x80 | n])
        elif n < 65536: tete += bytes([0x80 | 126]) + struct.pack('>H', n)
        else: tete += bytes([0x80 | 127]) + struct.pack('>Q', n)
        m = os.urandom(4)
        self.s.sendall(tete + m + bytes(b ^ m[i % 4] for i, b in enumerate(d)))

    def recevoir(self, delai=None):
        self.s.settimeout(delai)
        morceaux = []
        while True:
            a, b = self._lire(2)
            fin, op, n = a & 0x80, a & 0x0f, b & 0x7f
            if n == 126: n = struct.unpack('>H', self._lire(2))[0]
            elif n == 127: n = struct.unpack('>Q', self._lire(8))[0]
            d = self._lire(n)
            if op == 9:            # ping
                continue
            if op == 8:
                raise ConnectionError('WebSocket fermé par Chrome')
            morceaux.append(d)
            if fin:
                return b''.join(morceaux).decode('utf-8', 'replace')


class Chrome:
    def __init__(self, taille=(1440, 900), dpr=1, tactile=False, port=None, autoplay=True, gpu=True, extra=()):
        self.taille, self.dpr, self.tactile = taille, dpr, tactile
        self.port = port or (9300 + os.getpid() % 500)
        self.profil = tempfile.mkdtemp(prefix='cdp-')
        args = [CHROME, '--headless=new', '--remote-debugging-port=%d' % self.port, '--user-data-dir=' + self.profil,
                '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--mute-audio',
                '--window-size=%d,%d' % taille, '--force-device-scale-factor=%s' % dpr,
                '--disable-background-timer-throttling', '--disable-renderer-backgrounding',
                '--disable-backgrounding-occluded-windows']
        if gpu: args += ['--use-angle=metal', '--enable-gpu', '--ignore-gpu-blocklist']
        if autoplay: args += ['--autoplay-policy=no-user-gesture-required']
        args += list(extra) + ['about:blank']
        self.proc = subprocess.Popen(args, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.id = 0
        self.erreurs, self.console = [], []
        cible = None
        for _ in range(100):
            try:
                pages = json.load(urllib.request.urlopen('http://127.0.0.1:%d/json' % self.port, timeout=1))
                cible = next((p for p in pages if p.get('type') == 'page'), None)
                if cible: break
            except Exception:
                pass
            time.sleep(0.1)
        if not cible:
            self.fermer()
            raise RuntimeError('Chrome ne répond pas')
        self.ws = WS(cible['webSocketDebuggerUrl'])
        for d in ('Page', 'Runtime', 'Log', 'Network'):
            self.cmd(d + '.enable')
        self.cmd('Emulation.setDeviceMetricsOverride', width=taille[0], height=taille[1], deviceScaleFactor=dpr, mobile=bool(tactile))
        if tactile:
            self.cmd('Emulation.setTouchEmulationEnabled', enabled=True)
            self.cmd('Emulation.setEmitTouchEventsForMouse', enabled=True, configuration='mobile')

    def __enter__(self): return self
    def __exit__(self, *a): self.fermer()

    def fermer(self):
        try: self.proc.terminate(); self.proc.wait(timeout=5)
        except Exception:
            try: self.proc.kill()
            except Exception: pass
        shutil.rmtree(self.profil, ignore_errors=True)

    def _evenement(self, m):
        me, p = m.get('method'), m.get('params', {})
        if me == 'Runtime.exceptionThrown':
            d = p.get('exceptionDetails', {})
            self.erreurs.append('exception : ' + str((d.get('exception') or {}).get('description') or d.get('text')))
        elif me == 'Runtime.consoleAPICalled':
            txt = ' '.join(str(a.get('value', a.get('description', ''))) for a in p.get('args', []))
            self.console.append((p.get('type'), txt))
            if p.get('type') == 'error': self.erreurs.append('console.error : ' + txt)
        elif me == 'Log.entryAdded':
            e = p.get('entry', {})
            if e.get('level') == 'error': self.erreurs.append('journal : %s (%s)' % (e.get('text'), e.get('url', '')))
        elif me == 'Network.loadingFailed' and not p.get('canceled'):
            self.erreurs.append('réseau : ' + str(p.get('errorText')) + ' ' + str(p.get('requestId')))

    def cmd(self, methode, delai=30, **params):
        self.id += 1
        moi = self.id
        self.ws.envoyer(json.dumps({'id': moi, 'method': methode, 'params': params}))
        fin = time.time() + delai
        while True:
            m = json.loads(self.ws.recevoir(max(0.1, fin - time.time())))
            if m.get('id') == moi:
                if 'error' in m: raise RuntimeError('%s : %s' % (methode, m['error']))
                return m.get('result', {})
            self._evenement(m)

    def pomper(self, duree):
        """Laisse tourner la page `duree` secondes en relevant les événements."""
        fin = time.time() + duree
        while time.time() < fin:
            try: self._evenement(json.loads(self.ws.recevoir(max(0.01, fin - time.time()))))
            except (socket.timeout, TimeoutError): break
    attendre = pomper

    def ouvrir(self, url, attente=1.5):
        self.cmd('Page.navigate', url=url)
        fin = time.time() + 20
        while time.time() < fin:
            self.pomper(0.2)
            if self.js('document.readyState') == 'complete': break
        self.pomper(attente)

    def js(self, expr, attendre_promesse=True):
        r = self.cmd('Runtime.evaluate', expression=expr, returnByValue=True, awaitPromise=attendre_promesse, userGesture=True)
        if 'exceptionDetails' in r:
            raise RuntimeError('JS : ' + str(r['exceptionDetails'].get('exception', {}).get('description') or r['exceptionDetails'].get('text')))
        return r.get('result', {}).get('value')

    def defiler(self, y, attente=0.8):
        self.js('(function(){var l=window.__h4&&window.__h4.lenis; if(l&&l.scrollTo) l.scrollTo(%d,{immediate:true,force:true}); window.scrollTo(0,%d);})()' % (y, y))
        self.pomper(attente)

    def molette(self, dy, n=1, x=None, y=None, pause=0.016):
        """n crans de molette de dy pixels : de vrais événements wheel (ceux que Lenis lisse)."""
        x = self.taille[0] / 2 if x is None else x
        y = self.taille[1] / 2 if y is None else y
        for _ in range(n):
            self.cmd('Input.dispatchMouseEvent', type='mouseWheel', x=x, y=y, deltaX=0, deltaY=dy)
            self.pomper(pause)

    def cliquer(self, x, y):
        for t in ('mousePressed', 'mouseReleased'):
            self.cmd('Input.dispatchMouseEvent', type=t, x=x, y=y, button='left', clickCount=1)

    def cliquer_sel(self, sel):
        r = self.js('(function(){var e=document.querySelector(%s); if(!e) return null; var r=e.getBoundingClientRect(); return [r.left+r.width/2, r.top+r.height/2];})()' % json.dumps(sel))
        if not r: raise RuntimeError('introuvable : ' + sel)
        self.cliquer(r[0], r[1])

    def survoler(self, x, y):
        self.cmd('Input.dispatchMouseEvent', type='mouseMoved', x=x, y=y)

    def touche(self, cle, code=None):
        for t in ('keyDown', 'keyUp'):
            self.cmd('Input.dispatchKeyEvent', type=t, key=cle, code=code or cle, text=cle if (t == 'keyDown' and len(cle) == 1) else '')

    def taille_fenetre(self, l, h, dpr=None, tactile=None):
        self.cmd('Emulation.setDeviceMetricsOverride', width=l, height=h, deviceScaleFactor=dpr or self.dpr,
                 mobile=bool(self.tactile if tactile is None else tactile))

    def capture(self, chemin, pleine=False):
        p = {'format': 'png', 'captureBeyondViewport': bool(pleine)}
        if pleine:
            m = self.cmd('Page.getLayoutMetrics')['cssContentSize']
            p['clip'] = {'x': 0, 'y': 0, 'width': m['width'], 'height': m['height'], 'scale': 1}
        d = self.cmd('Page.captureScreenshot', **p)['data']
        with open(chemin, 'wb') as f: f.write(base64.b64decode(d))
        return chemin


if __name__ == '__main__':
    import argparse
    ap = argparse.ArgumentParser(description='Capture une page avec Chrome headless (CDP).')
    ap.add_argument('url'); ap.add_argument('sortie')
    ap.add_argument('--taille', default='1440x900'); ap.add_argument('--attente', type=float, default=2)
    ap.add_argument('--defile', type=int, default=0); ap.add_argument('--js'); ap.add_argument('--tactile', action='store_true')
    ap.add_argument('--pleine', action='store_true')
    a = ap.parse_args()
    l, h = (int(x) for x in a.taille.lower().split('x'))
    with Chrome(taille=(l, h), tactile=a.tactile) as c:
        c.ouvrir(a.url, a.attente)
        if a.defile: c.defiler(a.defile)
        if a.js: print(json.dumps(c.js(a.js), ensure_ascii=False, indent=1))
        c.capture(a.sortie, a.pleine)
        for e in c.erreurs: print('ERREUR', e, file=sys.stderr)
