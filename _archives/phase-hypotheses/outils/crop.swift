import AppKit
let a = CommandLine.arguments
let img = NSImage(contentsOfFile: a[1])!
var r = CGRect(x: Double(a[3])!, y: Double(a[4])!, width: Double(a[5])!, height: Double(a[6])!)
let cg = img.cgImage(forProposedRect: nil, context: nil, hints: nil)!
let c = cg.cropping(to: r)!
let rep = NSBitmapImageRep(cgImage: c)
try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: a[2]))
