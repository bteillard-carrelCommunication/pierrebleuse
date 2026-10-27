import sys
S=sys.argv[1]; W,H=992,619
a=open(S+'/b3.rgb','rb').read(); b=open(S+'/b4.rgb','rb').read(); m=open(S+'/fg.rgb','rb').read()
def same(x,y):
    i=(y*W+x)*3; return abs(a[i]-b[i])+abs(a[i+1]-b[i+1])+abs(a[i+2]-b[i+2])<30
for x0,x1 in [(722,752),(782,812),(843,872)]:
    print('stripe',x0,x1)
    runs=[];cur=None
    for y in range(H):
        cnt=sum(same(x,y) for x in range(x0+3,x1-2))
        fgc=sum(m[(y*W+x)*3]>127 for x in range(x0+3,x1-2))
        st=(cnt>12, fgc>12)
        if st!=cur:
            runs.append((y,st)); cur=st
    print(runs)
