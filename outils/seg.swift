import AppKit
import Vision
import CoreImage
// seg <in> <outPrefix> : masques Vision (instances de premier plan + personnes)
let a = CommandLine.arguments
let url = URL(fileURLWithPath: a[1])
let handler = VNImageRequestHandler(url: url)
let req = VNGenerateForegroundInstanceMaskRequest()
let preq = VNGeneratePersonInstanceMaskRequest()
try handler.perform([req, preq])
let ctx = CIContext()
func save(_ pb: CVPixelBuffer, _ path: String) {
  let ci = CIImage(cvPixelBuffer: pb)
  let cg = ctx.createCGImage(ci, from: ci.extent)!
  let rep = NSBitmapImageRep(cgImage: cg)
  try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: path))
}
if let obs = req.results?.first {
  print("fg instances:", obs.allInstances.count)
  for i in obs.allInstances {
    let pb = try obs.generateScaledMaskForImage(forInstances: IndexSet(integer: i), from: handler)
    save(pb, "\(a[2])-fg\(i).png")
  }
}
if let obs = preq.results?.first {
  print("person instances:", obs.allInstances.count)
  for i in obs.allInstances {
    let pb = try obs.generateScaledMaskForImage(forInstances: IndexSet(integer: i), from: handler)
    save(pb, "\(a[2])-p\(i).png")
  }
}
