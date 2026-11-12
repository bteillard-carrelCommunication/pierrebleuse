import Foundation
import ImageIO
import CoreGraphics
// match <liste candidats> <cible1> <cible2>... : NCC en niveaux de gris, tolérant au recadrage
func gray(_ cg: CGImage, _ w: Int, _ h: Int) -> [Float] {
  var buf = [UInt8](repeating: 0, count: w*h)
  let ctx = CGContext(data: &buf, width: w, height: h, bitsPerComponent: 8, bytesPerRow: w, space: CGColorSpaceCreateDeviceGray(), bitmapInfo: CGImageAlphaInfo.none.rawValue)!
  ctx.interpolationQuality = .high
  ctx.draw(cg, in: CGRect(x: 0, y: 0, width: w, height: h))
  return buf.map { Float($0) }
}
func load(_ path: String, _ maxPx: Int) -> CGImage? {
  guard let src = CGImageSourceCreateWithURL(URL(fileURLWithPath: path) as CFURL, nil) else { return nil }
  let opts: [CFString: Any] = [kCGImageSourceCreateThumbnailFromImageAlways: true, kCGImageSourceThumbnailMaxPixelSize: maxPx, kCGImageSourceCreateThumbnailWithTransform: true]
  return CGImageSourceCreateThumbnailAtIndex(src, 0, opts as CFDictionary)
}
let args = CommandLine.arguments
let lines = try! String(contentsOfFile: args[1], encoding: .utf8).split(separator: "\n").map(String.init)
let CW = 160
struct T { let name: String; let cg: CGImage }
let targets = args[2...].map { T(name: ($0 as NSString).lastPathComponent, cg: load($0, 992)!) }
let fracs: [Float] = [1.0, 0.92, 0.85, 0.78, 0.7, 0.62, 0.55, 0.48, 0.4]
// gabarits pré-calculés : (frac, w, h, pixels centrés-normalisés)
var tpl: [[(Float, Int, Int, [Float])]] = []
for t in targets {
  var arr: [(Float, Int, Int, [Float])] = []
  let ratio = Float(t.cg.height) / Float(t.cg.width)
  for f in fracs {
    let w = Int(Float(CW) * f), h = max(4, Int(Float(w) * ratio))
    var g = gray(t.cg, w, h)
    let m = g.reduce(0, +) / Float(g.count)
    g = g.map { $0 - m }
    let n = sqrt(g.reduce(0) { $0 + $1 * $1 })
    arr.append((f, w, h, g.map { $0 / max(n, 1e-6) }))
  }
  tpl.append(arr)
}
for line in lines {
  let parts = line.split(separator: "|", maxSplits: 4).map(String.init)
  guard parts.count == 5, let cg = load(parts[4], CW) else { continue }
  // ramener la plus grande dimension à CW de large en paysage
  let cw = CW, ch = Int(Float(CW) * Float(cg.height) / Float(cg.width))
  let c = gray(cg, cw, ch)
  var out = [String]()
  for (ti, arr) in tpl.enumerated() {
    var best: Float = -1; var bestDesc = ""
    for (f, w, h, g) in arr {
      if w > cw || h > ch { continue }
      var y = 0
      while y <= ch - h {
        var x = 0
        while x <= cw - w {
          var s: Float = 0, s2: Float = 0, dot: Float = 0
          for j in 0..<h {
            let row = (y + j) * cw + x, trow = j * w
            for i in 0..<w {
              let v = c[row + i]
              s += v; s2 += v * v; dot += v * g[trow + i]
            }
          }
          let n = Float(w * h)
          let varc = s2 - s * s / n
          let ncc = dot / max(sqrt(varc), 1e-6)
          if ncc > best { best = ncc; bestDesc = "f=\(f) x=\(x) y=\(y)" }
          x += 2
        }
        y += 2
      }
    }
    out.append(String(format: "%@=%.3f(%@)", targets[ti].name, best, bestDesc))
  }
  print(out.joined(separator: " "), "|", parts[0] + "x" + parts[1], "|", parts[4])
}
