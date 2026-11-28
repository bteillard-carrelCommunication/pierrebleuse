#!/usr/bin/env python3
"""faux-services.py : de faux services extérieurs, pour essayer Pupitre sans rien demander à personne.

    python3 pupitre/tests/faux-services.py <port> [<port IMAP>]

Sert, sur 127.0.0.1 :
  /metricool/…     une fausse API de Metricool : deux marques (111 « Artiste d'essai », 222 « Comptes d'essai »),
                   leurs publications Instagram et Facebook avec leurs mesures ; l'en-tête X-Mc-Auth doit valoir
                   « jeton-essai », le paramètre userId « 42 » ;
  /actualites/…    un faux flux d'actualités (RSS), tel que le rend une recherche d'actualités ;
  /pages/…         de fausses pages web citées par le flux.
Et, si un second port est donné, une fausse boîte aux lettres IMAP (sans chiffrement) : une réponse, un message
d'absence et un rejet ; identifiant « essai », mot de passe « secret ».

Les essais (tests/nouveautes.py) le lancent eux-mêmes et l'arrêtent. Rien ici n'est une donnée réelle.
"""
import datetime, json, socketserver, sys, threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

AUJ = datetime.datetime.now().replace(microsecond=0)


def jour(n, heure=18):
    return (AUJ - datetime.timedelta(days=n)).replace(hour=heure, minute=0, second=0)


MARQUES = [
    {'id': 111, 'userId': 42, 'label': 'Artiste d\'essai', 'instagram': 'artiste.essai', 'facebook': 'Artiste Essai', 'linkedinCompany': None},
    {'id': 222, 'userId': 42, 'label': 'Comptes d\'essai', 'instagram': 'compte.test', 'facebook': None},
]
INSTAGRAM = {
    '111': [
        {'postId': 'ig-1', 'type': 'FEED_IMAGE', 'publishedAt': {'dateTime': jour(3).isoformat(), 'timezone': 'Europe/Paris'}, 'url': 'https://www.instagram.com/p/ig-1/',
         'content': 'Tonight in Madrid with the Orquesta Nacional: Ravel and Debussy. #essai', 'imageUrl': 'https://example.com/ig-1.jpg',
         'likes': 420, 'comments': 31, 'shares': 12, 'saved': 9, 'interactions': 472, 'engagement': 6.21, 'reach': 7600, 'impressionsTotal': 9100, 'views': 0},
        {'postId': 'ig-2', 'type': 'CAROUSEL_ALBUM', 'publishedAt': {'dateTime': jour(12).isoformat(), 'timezone': 'Europe/Paris'}, 'url': 'https://www.instagram.com/p/ig-2/',
         'content': 'Rehearsal week. Photos from the hall.', 'imageUrl': 'https://example.com/ig-2.jpg',
         'likes': 150, 'comments': 6, 'shares': 2, 'saved': 4, 'interactions': 162, 'engagement': 2.4, 'reach': 4100, 'impressionsTotal': 5200},
        {'postId': 'ig-3', 'type': 'FEED_IMAGE', 'publishedAt': {'dateTime': jour(25).isoformat(), 'timezone': 'Europe/Paris'}, 'url': 'https://www.instagram.com/p/ig-3/',
         'content': 'A new review in the press.', 'likes': 90, 'comments': 2, 'shares': 0, 'saved': 1, 'interactions': 93, 'engagement': 1.6, 'reach': 3000, 'impressionsTotal': 3600},
    ],
    '222': [
        {'postId': 'ig-test-1', 'type': 'FEED_IMAGE', 'publishedAt': {'dateTime': jour(1).isoformat(), 'timezone': 'Europe/Paris'}, 'url': 'https://www.instagram.com/p/ig-test-1/',
         'content': 'Publication de test.', 'likes': 1, 'comments': 0, 'shares': 0, 'saved': 0, 'interactions': 1, 'engagement': 0.1, 'reach': 12, 'impressionsTotal': 14},
    ],
}
FACEBOOK = {
    '111': [
        {'postId': 'fb-1', 'created': {'dateTime': jour(3, 19).isoformat(), 'timezone': 'Europe/Paris'}, 'timestamp': int(jour(3, 19).timestamp()), 'link': 'https://www.facebook.com/essai/posts/fb-1',
         'text': 'Ce soir à Madrid.', 'picture': 'https://example.com/fb-1.jpg', 'reactions': 64, 'comments': 5, 'shares': 7, 'impressions': 2400, 'impressionsUnique': 1900, 'clicks': 40, 'engagement': 3.1},
    ],
    '222': [],
}
REELS = {'111': [{'reelId': 'reel-1', 'publishedAt': {'dateTime': jour(7).isoformat(), 'timezone': 'Europe/Paris'}, 'url': 'https://www.instagram.com/reel/reel-1/', 'content': 'Backstage, thirty seconds.',
                  'likes': 800, 'comments': 44, 'shares': 60, 'saved': 21, 'interactions': 925, 'engagement': 9.8, 'reach': 12000, 'views': 15400}], '222': []}

RSS = '''<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>"Pierre Bleuse" - Actualités</title>
<item><title>Pierre Bleuse triomphe à Madrid - Le Journal d'essai</title><link>http://127.0.0.1:%(port)d/pages/critique-madrid.html</link><guid isPermaLink="false">essai-1</guid>
<pubDate>%(d1)s</pubDate><description>&lt;a href="x"&gt;Pierre Bleuse triomphe à Madrid&lt;/a&gt;&amp;nbsp;&lt;font color="#6f6f6f"&gt;Le Journal d'essai&lt;/font&gt;</description><source url="https://journal.essai.test">Le Journal d'essai</source></item>
<item><title>Saison 2027 : les chefs invités - Gazette d'essai</title><link>http://127.0.0.1:%(port)d/pages/saison-2027.html</link><guid isPermaLink="false">essai-2</guid>
<pubDate>%(d2)s</pubDate><description>Annonce de saison</description><source url="https://gazette.essai.test">Gazette d'essai</source></item>
</channel></rss>'''
PAGES = {
    'critique-madrid.html': '<html lang="fr"><head><title>Pierre Bleuse triomphe à Madrid</title><meta property="og:site_name" content="Le Journal d\'essai"><meta name="description" content="Compte rendu du concert de Madrid."></head>'
                            '<body><article><h1>Pierre Bleuse triomphe à Madrid</h1><p>Sous la baguette de Pierre Bleuse, l\'orchestre a donné un Ravel d\'une rare clarté.</p></article></body></html>',
    'saison-2027.html': '<html lang="fr"><head><title>Saison 2027 : les chefs invités</title></head><body><p>Parmi les chefs invités de la saison : Pierre Bleuse, pour deux programmes.</p></body></html>',
}


class Web(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def rendre(self, code, corps, genre='application/json'):
        if not isinstance(corps, (bytes, str)):
            corps = json.dumps(corps)
        if isinstance(corps, str):
            corps = corps.encode()
        self.send_response(code)
        self.send_header('Content-Type', genre + '; charset=utf-8')
        self.send_header('Content-Length', str(len(corps)))
        self.end_headers()
        self.wfile.write(corps)

    def do_GET(self):
        u = urlparse(self.path)
        q = {k: v[0] for k, v in parse_qs(u.query).items()}
        if u.path.startswith('/metricool/'):
            if self.headers.get('X-Mc-Auth') != 'jeton-essai' or q.get('userId') != '42':
                return self.rendre(401, {'error': 'unauthorized'})
            chemin = u.path[len('/metricool'):]
            if chemin == '/admin/simpleProfiles':
                return self.rendre(200, MARQUES)
            marque = q.get('blogId', '')
            if chemin == '/v2/analytics/posts/instagram':
                return self.rendre(200, {'data': INSTAGRAM.get(marque, [])})
            if chemin == '/v2/analytics/reels/instagram':
                return self.rendre(200, {'data': REELS.get(marque, [])})
            if chemin == '/v2/analytics/posts/facebook':
                return self.rendre(200, {'data': FACEBOOK.get(marque, [])})
            return self.rendre(404, {'error': 'not connected'})
        if u.path.startswith('/actualites/'):
            fmt = '%a, %d %b %Y %H:%M:%S GMT'
            return self.rendre(200, RSS % {'port': self.server.server_address[1], 'd1': jour(2).strftime(fmt), 'd2': jour(5).strftime(fmt)}, 'application/rss+xml')
        if u.path.startswith('/pages/') and u.path[7:] in PAGES:
            return self.rendre(200, PAGES[u.path[7:]], 'text/html')
        self.rendre(404, 'introuvable', 'text/plain')


# ---------- une fausse boîte IMAP : juste ce que Pupitre lui demande ----------
def message(de, sujet, corps, n, extra=''):
    date = jour(n).strftime('%a, %d %b %Y %H:%M:%S +0100')
    return ('From: %s\r\nTo: contact@essai.pupitre.test\r\nSubject: %s\r\nDate: %s\r\nMessage-ID: <essai-%d@faux.test>\r\n%sMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n'
            'Content-Transfer-Encoding: 8bit\r\n\r\n%s\r\n' % (de, sujet, date, n, extra, corps)).encode()


BOITE = {
    1: message('"Camille Essai" <com@essai.pupitre.test>', 'Re: Photos and videos: Concert d\'essai', 'Bonjour,\r\nVoici les photos, merci !\r\nCamille', 1, 'In-Reply-To: <x@y>\r\n'),
    2: message('Service presse <presse@essai.pupitre.test>', '=?UTF-8?B?UsOpcG9uc2UgYXV0b21hdGlxdWUgOiBhYnNlbnQ=?=', 'Je suis absent jusqu\'au 12.', 2, 'Auto-Submitted: auto-replied\r\n'),
    3: message('Mail Delivery System <MAILER-DAEMON@essai.pupitre.test>', 'Undelivered Mail Returned to Sender', 'The mail could not be delivered to inconnu@essai.pupitre.test: user unknown.', 3),
}


class Imap(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'* OK faux IMAP pret\r\n')
        connecte = False
        while True:
            ligne = self.rfile.readline()
            if not ligne:
                return
            morceaux = ligne.decode('utf-8', 'replace').strip().split(' ', 2)
            if len(morceaux) < 2:
                continue
            tag, commande = morceaux[0], morceaux[1].upper()
            reste = morceaux[2] if len(morceaux) > 2 else ''
            if commande == 'UID' and reste:
                commande = 'UID ' + reste.split(' ', 1)[0].upper()
                reste = reste.split(' ', 1)[1] if ' ' in reste else ''
            if commande == 'LOGIN':
                connecte = reste.replace('"', '') == 'essai secret'
                self.wfile.write(('%s %s\r\n' % (tag, 'OK LOGIN fait' if connecte else 'NO identifiants refuses')).encode())
            elif commande == 'LOGOUT':
                self.wfile.write(('* BYE\r\n%s OK\r\n' % tag).encode())
                return
            elif not connecte:
                self.wfile.write(('%s NO pas connecte\r\n' % tag).encode())
            elif commande in ('SELECT', 'EXAMINE'):
                self.wfile.write(('* %d EXISTS\r\n* OK [UIDVALIDITY 1] ok\r\n%s OK [READ-ONLY] fait\r\n' % (len(BOITE), tag)).encode())
            elif commande == 'UID SEARCH':
                self.wfile.write(('* SEARCH %s\r\n%s OK fait\r\n' % (' '.join(str(u) for u in sorted(BOITE)), tag)).encode())
            elif commande == 'UID FETCH':
                uid = int(reste.split(' ', 1)[0])
                if uid in BOITE:
                    brut = BOITE[uid]
                    self.wfile.write(('* %d FETCH (UID %d BODY[] {%d}\r\n' % (uid, uid, len(brut))).encode() + brut + b')\r\n')
                self.wfile.write(('%s OK fait\r\n' % tag).encode())
            else:
                self.wfile.write(('%s OK\r\n' % tag).encode())


class ServeurImap(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8798
    if len(sys.argv) > 2:
        imap = ServeurImap(('127.0.0.1', int(sys.argv[2])), Imap)
        threading.Thread(target=imap.serve_forever, daemon=True).start()
    web = ThreadingHTTPServer(('127.0.0.1', port), Web)
    print('faux services sur le port %d' % port, flush=True)
    web.serve_forever()
