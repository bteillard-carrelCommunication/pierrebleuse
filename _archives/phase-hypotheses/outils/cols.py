import sys
S=sys.argv[1]; W,H=992,619
a=open(S+'/b3.rgb','rb').read(); b=open(S+'/b4.rgb','rb').read()
prof=[]
for x in range(W):
    n=0
    for y in range(H):
        i=(y*W+x)*3
        if abs(a[i]-b[i])+abs(a[i+1]-b[i+1])+abs(a[i+2]-b[i+2])>45: n+=1
    prof.append(n/H)
# segments où prof>0.5
segs=[];inside=False
for x,p in enumerate(prof):
    if p>0.5 and not inside: s=x; inside=True
    if p<=0.5 and inside: segs.append((s,x-1)); inside=False
if inside: segs.append((s,W-1))
print(segs)
print([ (s,e,e-s+1) for s,e in segs])
print('starts diff', [segs[i+1][0]-segs[i][0] for i in range(len(segs)-1)])
