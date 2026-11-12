import AppKit
// sheet <out.png> <cols> <cellW> <img1> <img2> ...  : planche-contact
let a = CommandLine.arguments
let out = a[1]; let cols = Int(a[2])!; let cw = CGFloat(Double(a[3])!)
let paths = Array(a[4...])
let imgs = paths.compactMap { NSImage(contentsOfFile: $0) }
let ch = cw * (imgs.first!.size.height / imgs.first!.size.width)
let rows = (imgs.count + cols - 1) / cols
let W = cw * CGFloat(cols), H = (ch + 18) * CGFloat(rows)
let img = NSImage(size: NSSize(width: W, height: H))
img.lockFocus()
NSColor.darkGray.setFill(); NSRect(x: 0, y: 0, width: W, height: H).fill()
for (i, im) in imgs.enumerated() {
  let c = i % cols, r = i / cols
  let y = H - CGFloat(r + 1) * (ch + 18)
  im.draw(in: NSRect(x: CGFloat(c) * cw, y: y + 18, width: cw - 4, height: ch - 4))
  let label = (paths[i] as NSString).lastPathComponent as NSString
  label.draw(at: NSPoint(x: CGFloat(c) * cw + 4, y: y + 2), withAttributes: [.foregroundColor: NSColor.white, .font: NSFont.systemFont(ofSize: 11)])
}
img.unlockFocus()
let rep = NSBitmapImageRep(data: img.tiffRepresentation!)!
try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: out))
