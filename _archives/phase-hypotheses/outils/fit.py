import sys
S=sys.argv[1]; W,H=992,619
a=open(S+'/b3.rgb','rb').read(); b=open(S+'/b4.rgb','rb').read()
def px(buf,x,y): i=(y*W+x)*3; return buf[i],buf[i+1],buf[i+2]
# régression out = p*rot + q*orig + c par canal (moindres carrés 3x3, sans numpy)
def solve(A,y):
    # normal equations
    n=len(A[0]); M=[[sum(r[i]*r[j] for r in A) for j in range(n)] for i in range(n)]
    v=[sum(r[i]*t for r,t in zip(A,y)) for i in range(n)]
    for i in range(n):
        p=max(range(i,n),key=lambda r:abs(M[r][i])); M[i],M[p]=M[p],M[i]; v[i],v[p]=v[p],v[i]
        for r in range(n):
            if r!=i:
                f=M[r][i]/M[i][i]; M[r]=[M[r][k]-f*M[i][k] for k in range(n)]; v[r]-=f*v[i]
    return [v[i]/M[i][i] for i in range(n)]
for shift in [(0,0),(1,0),(0,1),(1,1),(-1,0),(0,-1)]:
  rows=[[],[],[]]; ys=[[],[],[]]
  for k in range(0,9):
    x0=round(-1.5+60.25*k)
    for x in range(max(0,x0+5),x0+26,2):
        for y in range(5,H-5,3):
            o=px(b,x,y); r=px(a,min(W-1,max(0,W-1-x+shift[0])),min(H-1,max(0,H-1-y+shift[1]))); s=px(a,x,y)
            for c in range(3):
                rows[c].append([r[c],s[c],1.0]); ys[c].append(o[c])
  res=[]
  for c in range(3):
    co=solve(rows[c],ys[c])
    err=sum((co[0]*r[0]+co[1]*r[1]+co[2]-t)**2 for r,t in zip(rows[c],ys[c]))/len(ys[c])
    res.append((round(co[0],3),round(co[1],3),round(co[2],1),round(err**.5,1)))
  print(shift,res)
