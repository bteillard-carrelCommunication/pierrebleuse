import AppKit
// dump RGB8 brut : raw <in> <out>
let a = CommandLine.arguments
let img = NSImage(contentsOfFile: a[1])!
let cg = img.cgImage(forProposedRect: nil, context: nil, hints: nil)!
let w = cg.width, h = cg.height
var buf = [UInt8](repeating: 0, count: w*h*4)
let ctx = CGContext(data: &buf, width: w, height: h, bitsPerComponent: 8, bytesPerRow: w*4, space: CGColorSpaceCreateDeviceRGB(), bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)!
ctx.draw(cg, in: CGRect(x: 0, y: 0, width: w, height: h))
var rgb = [UInt8](); rgb.reserveCapacity(w*h*3)
for i in 0..<(w*h) { rgb.append(buf[i*4]); rgb.append(buf[i*4+1]); rgb.append(buf[i*4+2]) }
FileManager.default.createFile(atPath: a[2], contents: Data(rgb))
print(w, h)
