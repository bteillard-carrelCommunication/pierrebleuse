import Foundation
import CoreGraphics
import ImageIO
import UniformTypeIdentifiers
// pdfimg <fichier.pdf> <dossier sortie> <préfixe> : extrait les images XObject >= 1000 px
let a = CommandLine.arguments
guard let doc = CGPDFDocument(URL(fileURLWithPath: a[1]) as CFURL) else { print("PDF illisible"); exit(1) }
var n = 0
for pi in 1...doc.numberOfPages {
  guard let page = doc.page(at: pi), let pdict = page.dictionary else { continue }
  var res: CGPDFDictionaryRef?; guard CGPDFDictionaryGetDictionary(pdict, "Resources", &res), let r = res else { continue }
  var xo: CGPDFDictionaryRef?; guard CGPDFDictionaryGetDictionary(r, "XObject", &xo), let x = xo else { continue }
  var keys: [String] = []
  CGPDFDictionaryApplyBlock(x, { key, _, _ in keys.append(String(cString: key)); return true }, nil)
  for k in keys {
    var st: CGPDFStreamRef?; guard CGPDFDictionaryGetStream(x, k, &st), let s = st, let sd = CGPDFStreamGetDictionary(s) else { continue }
    var w: CGPDFInteger = 0, h: CGPDFInteger = 0
    CGPDFDictionaryGetInteger(sd, "Width", &w); CGPDFDictionaryGetInteger(sd, "Height", &h)
    if w < 1000 && h < 1000 { continue }
    var fmt = CGPDFDataFormat.raw
    guard let data = CGPDFStreamCopyData(s, &fmt) as Data? else { continue }
    let base = "\(a[2])/\(a[3])-p\(pi)-\(k)"
    if fmt == .jpegEncoded { try? data.write(to: URL(fileURLWithPath: base + ".jpg")); print("jpg", pi, k, w, h); n += 1; continue }
    if fmt == .JPEG2000 { try? data.write(to: URL(fileURLWithPath: base + ".jp2")); print("jp2", pi, k, w, h); n += 1; continue }
    // brut : 8 bits RGB ou gris
    let comps = data.count / (w * h)
    guard comps == 3 || comps == 1 || comps == 4 else { print("brut ?", pi, k, w, h, data.count); continue }
    let cs = comps == 1 ? CGColorSpaceCreateDeviceGray() : (comps == 4 ? CGColorSpaceCreateDeviceCMYK() : CGColorSpaceCreateDeviceRGB())
    guard let prov = CGDataProvider(data: data as CFData),
          let img = CGImage(width: w, height: h, bitsPerComponent: 8, bitsPerPixel: 8 * comps, bytesPerRow: w * comps, space: cs, bitmapInfo: CGBitmapInfo(rawValue: 0), provider: prov, decode: nil, shouldInterpolate: false, intent: .defaultIntent) else { continue }
    let dest = CGImageDestinationCreateWithURL(URL(fileURLWithPath: base + ".png") as CFURL, UTType.png.identifier as CFString, 1, nil)!
    CGImageDestinationAddImage(dest, img, nil); CGImageDestinationFinalize(dest)
    print("png", pi, k, w, h, comps); n += 1
  }
}
print("total", n)
