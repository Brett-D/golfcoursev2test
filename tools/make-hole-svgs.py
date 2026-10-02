"""Rebuild the nine illustrated hole graphics (assets/images/holes/vector/hole-1..9.svg) from the printed scorecard PDF.

Usage:  pip install pymupdf  &&  python tools/make-hole-svgs.py "path/to/Agate Beach 8.22.pdf"

The outlines of the fairways, greens, tees, bunkers and water, and the position, size and tilt of every tree, are read
from the vector map on page 1 of the PDF. Par and yardage (men's tees) are typed into the HOLES table below.
"""
import pymupdf,os,math,random,sys
PDF=sys.argv[1] if len(sys.argv)>1 else "Agate Beach 8.22.pdf"
dr=pymupdf.open(PDF)[0].get_drawings()
W,H=400,714
OUT=os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)),"..","assets","images","holes","vector"))
os.makedirs(OUT,exist_ok=True)

def path_d(s):
    parts=[];cur=None
    for it in s["items"]:
        k=it[0]
        if k=="l":
            a,b=it[1],it[2]
            if cur is None or abs(cur.x-a.x)>1e-3 or abs(cur.y-a.y)>1e-3: parts.append(f"M{a.x:.2f} {a.y:.2f}")
            parts.append(f"L{b.x:.2f} {b.y:.2f}"); cur=b
        elif k=="c":
            a,b,c,e=it[1:5]
            if cur is None or abs(cur.x-a.x)>1e-3 or abs(cur.y-a.y)>1e-3: parts.append(f"M{a.x:.2f} {a.y:.2f}")
            parts.append(f"C{b.x:.2f} {b.y:.2f} {c.x:.2f} {c.y:.2f} {e.x:.2f} {e.y:.2f}"); cur=e
        elif k=="re":
            r=it[1]; parts.append(f"M{r.x0:.2f} {r.y0:.2f}H{r.x1:.2f}V{r.y1:.2f}H{r.x0:.2f}Z")
    return "".join(parts)+"Z"
def poly_points(s,n=12):
    pts=[]
    for it in s["items"]:
        if it[0]=="l": pts+=[(it[1].x,it[1].y),(it[2].x,it[2].y)]
        elif it[0]=="c":
            a,b,c,e=[(q.x,q.y) for q in it[1:5]]
            for i in range(n+1):
                t=i/n;u=1-t
                pts.append((u**3*a[0]+3*u*u*t*b[0]+3*u*t*t*c[0]+t**3*e[0],u**3*a[1]+3*u*u*t*b[1]+3*u*t*t*c[1]+t**3*e[1]))
    return pts
def centre(s):
    r=s["rect"]; return ((r.x0+r.x1)/2,(r.y0+r.y1)/2)

def load_trees():
    import math
    d=pymupdf.open(PDF); p=d[0]
    cls={122:(0.42,"#2f6b3b"),126:(0.42,"#2f6b3b"),124:(0.42,"#1f5e31"),125:(0.42,"#1f5e31"),123:(0.42,"#1f5e31")}
    out=[]
    for i in p.get_image_info(xrefs=True):
        x=i["xref"]
        if x not in cls or i["bbox"][0]>280: continue
        a,b,c_,dd,e,f=i["transform"]
        w=math.hypot(a,b); h=math.hypot(c_,dd)
        cx=e+(a+c_)/2; cy=f+(b+dd)/2
        # the visible canopy is a lumpy oval inside the square picture, turned the same way as the picture
        out.append((cx,cy,0.25*w,0.31*h,math.degrees(math.atan2(b,a)),(x*7+int(cx*13+cy*29))%3,cls[x][1]))
    return out
TREES=load_trees()
HOLES={
 1:dict(rough=2,fairway=26,green=24,tees=[25],bunkers=[],water=[],white=157,red=165,par=4,yards=321,note=""),
 2:dict(rough=4,fairway=29,green=27,tees=[28],bunkers=[],water=[],white=174,red=169,par=4,yards=310,note=""),
 3:dict(rough=6,fairway=31,green=32,tees=[30],bunkers=[54,55],water=[],white=158,red=166,par=3,yards=157,note=""),
 4:dict(rough=10,fairway=34,green=35,tees=[33],bunkers=[],water=[],white=159,red=167,par=4,yards=351,note=""),
 5:dict(rough=12,fairway=37,green=36,tees=[38],bunkers=[],water=[],white=162,red=170,par=4,yards=378,note=""),
 6:dict(rough=14,fairway=41,green=42,tees=[39,40],bunkers=[],water=[],white=160,red=168,par=5,yards=501,note=""),
 7:dict(rough=16,fairway=45,green=43,tees=[44],bunkers=[],water=[175],white=161,red=173,par=4,yards=324,note=""),
 8:dict(rough=18,fairway=48,green=46,tees=[47],bunkers=[56],water=[],white=164,red=172,par=3,yards=115,note="SIGNATURE HOLE"),
 9:dict(rough=20,fairway=52,green=49,tees=[50,51],bunkers=[],water=[],white=163,red=171,par=5,yards=448,note=""),
}

def build(n,c):
    rough=dr[c["rough"]]; poly=poly_points(rough)
    own={c["rough"],c["fairway"],c["green"],*c["tees"],*c["bunkers"],*c["water"]}
    grn=centre(dr[c["green"]])
    tee=max((centre(dr[i]) for i in c["tees"]),key=lambda q:math.hypot(q[0]-grn[0],q[1]-grn[1]))
    white=centre(dr[c["white"]]); red=centre(dr[c["red"]])
    ang=math.degrees(math.atan2(grn[0]-tee[0],-(grn[1]-tee[1]))); rot=-ang
    a=math.radians(rot); ca,sa=math.cos(a),math.sin(a)
    # rotated (unscaled) extents of the rough decide the scale and centre
    rp=[(x*ca-y*sa, x*sa+y*ca) for x,y in poly]
    minx=min(p[0] for p in rp);maxx=max(p[0] for p in rp);miny=min(p[1] for p in rp);maxy=max(p[1] for p in rp)
    S=min(H*0.82/(maxy-miny), W*0.72/(maxx-minx))
    mxr=(minx+maxx)/2; myr=(miny+maxy)/2
    def fin(x,y):
        X=x*ca-y*sa; Y=x*sa+y*ca
        return (W/2+(X-mxr)*S, H/2+(Y-myr)*S)
    # transform string: translate(W/2,H/2) scale(S) translate(-mxr,-myr) rotate(rot)
    tf=f"translate({W/2} {H/2}) scale({S:.4f}) translate({-mxr:.3f} {-myr:.3f}) rotate({rot:.3f})"
    def inside(px,py):
        cc=False;m=len(poly)
        for i in range(m):
            x1,y1=poly[i];x2,y2=poly[(i+1)%m]
            if (y1>py)!=(y2>py) and px<(x2-x1)*(py-y1)/(y2-y1+1e-12)+x1: cc=not cc
        return cc
    # real trees: every tree picture on the printed map has a position, size and shade; use them as they are
    trees=[]
    for t in TREES:
        fx,fy=fin(t[0],t[1])
        if fx<-40 or fx>W+40 or fy<-40 or fy>H+40: continue
        trees.append((fx,fy,t[2]*S,t[3]*S,t[4]+rot,t[5],t[6]))
    trees.sort(key=lambda q:q[1])
    tuse="".join(f'<use href="#c{v}" color="{col}" transform="translate({x:.0f} {y:.0f}) rotate({an:.0f}) scale({rx/20:.2f} {ry/20:.2f})"/>' for x,y,rx,ry,an,v,col in trees)
    # neighbouring holes, faded, so the real trees sit on the same ground as on the printed map
    near=[]
    for i,s_ in enumerate(dr):
        if i<2 or i in own: continue
        f=s_.get("fill")
        if not f: continue
        cls=None
        if abs(f[0]-0.367)<.02 and abs(f[1]-0.592)<.02: cls="r"
        elif abs(f[0]-0.018)<.02 and abs(f[1]-0.334)<.02: cls="g"
        elif abs(f[0]-0.934)<.02 and abs(f[1]-0.879)<.02: cls="s"
        elif abs(f[0]-0.0)<.02 and abs(f[2]-0.835)<.02: cls="w"
        if not cls: continue
        near.append((cls,i))
    fill={"r":"#6aa43d","g":"#2b7a36","s":"#eadfc4","w":"#6cbbe3"}
    order={"r":0,"g":1,"s":2,"w":3}
    near.sort(key=lambda q:(order[q[0]],q[1]))
    neigh="".join(f'<path d="{path_d(dr[i])}" fill="{fill[c]}"/>' for c,i in near)    # play line along the middle of the hole
    txf,tyf=fin(*tee); gxf,gyf=fin(*grn)
    fb=(grn[0]+0.1,grn[1]+0.7)
    tees="".join(f'<path d="{path_d(dr[i])}" fill="#2a8a37" stroke="#1d6a2a" stroke-width=".1"/>' for i in c["tees"])
    bunk="".join(f'<path d="{path_d(dr[i])}" fill="url(#sand)" stroke="#b9a06a" stroke-width=".1"/>' for i in c["bunkers"])
    water="".join(f'<path d="{path_d(dr[i])}" fill="url(#water)" stroke="#d9f0fb" stroke-width=".25"/>' for i in c["water"])
    halo='stroke="#f6f3e3" stroke-width="4" paint-order="stroke" stroke-linejoin="round"'
    note=f'<text y="64" text-anchor="end" font-family="Inter, system-ui, sans-serif" font-size="11" font-weight="700" letter-spacing="1.5" fill="#0f2e4d" {halo}>{c["note"]}</text>' if c["note"] else ""
    svg=f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img" aria-label="Illustrated layout of hole {n}, par {c['par']}, {c['yards']} yards, tees at the bottom and the green at the top">
<defs>
<clipPath id="f"><rect width="{W}" height="{H}" rx="26"/></clipPath>
<linearGradient id="rough" x1="0" x2="1"><stop offset="0" stop-color="#6fae3f"/><stop offset=".5" stop-color="#5e9732"/><stop offset="1" stop-color="#6aa83a"/></linearGradient>
<linearGradient id="fair" x1="0" x2="1"><stop offset="0" stop-color="#2f7d2c"/><stop offset=".5" stop-color="#1f6b22"/><stop offset="1" stop-color="#2b7a2a"/></linearGradient>
<radialGradient id="green" cx=".45" cy=".4" r=".7"><stop offset="0" stop-color="#1f9a3c"/><stop offset="1" stop-color="#075a1f"/></radialGradient>
<radialGradient id="sand" cx=".4" cy=".35" r=".8"><stop offset="0" stop-color="#f8efd8"/><stop offset="1" stop-color="#e2cf9f"/></radialGradient>
<linearGradient id="water" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4fb4e6"/><stop offset="1" stop-color="#0a86cc"/></linearGradient>
<pattern id="mow" width="1.7" height="1.7" patternUnits="userSpaceOnUse"><rect width=".85" height="1.7" fill="#fff" opacity=".07"/></pattern>
<filter id="lift" x="-10%" y="-10%" width="120%" height="120%"><feDropShadow dx=".2" dy=".3" stdDeviation=".3" flood-color="#0b2d12" flood-opacity=".4"/></filter>
<g id="c0"><g fill="none" stroke="#173f25" stroke-width="3" stroke-linejoin="round"><circle cx="-8" cy="3" r="12"/><circle cx="9" cy="5" r="12"/><circle cx="0" cy="-8" r="13"/><circle cx="0" cy="2" r="10"/></g><g fill="currentColor"><circle cx="-8" cy="3" r="12"/><circle cx="9" cy="5" r="12"/><circle cx="0" cy="-8" r="13"/><circle cx="0" cy="2" r="10"/></g><g fill="#fff" fill-opacity=".16"><circle cx="-4" cy="-6" r="9"/><circle cx="7" cy="2" r="7"/></g><circle cx="-6" cy="-10" r="4" fill="#8ccf78" fill-opacity=".4"/></g>
<g id="c1"><g fill="none" stroke="#173f25" stroke-width="3" stroke-linejoin="round"><circle cx="-9" cy="-2" r="11"/><circle cx="8" cy="-4" r="12"/><circle cx="1" cy="8" r="12"/><circle cx="0" cy="1" r="9"/></g><g fill="currentColor"><circle cx="-9" cy="-2" r="11"/><circle cx="8" cy="-4" r="12"/><circle cx="1" cy="8" r="12"/><circle cx="0" cy="1" r="9"/></g><g fill="#fff" fill-opacity=".16"><circle cx="-5" cy="-3" r="8"/><circle cx="5" cy="5" r="8"/></g><circle cx="-1" cy="-6" r="4" fill="#8ccf78" fill-opacity=".4"/></g>
<g id="c2"><g fill="none" stroke="#173f25" stroke-width="3" stroke-linejoin="round"><circle cx="0" cy="-9" r="12"/><circle cx="-10" cy="4" r="11"/><circle cx="10" cy="3" r="12"/><circle cx="0" cy="3" r="10"/></g><g fill="currentColor"><circle cx="0" cy="-9" r="12"/><circle cx="-10" cy="4" r="11"/><circle cx="10" cy="3" r="12"/><circle cx="0" cy="3" r="10"/></g><g fill="#fff" fill-opacity=".16"><circle cx="0" cy="3" r="10"/><circle cx="-3" cy="-9" r="7"/></g><circle cx="-5" cy="-3" r="4" fill="#8ccf78" fill-opacity=".4"/></g>
</defs>
<g clip-path="url(#f)">
<rect width="{W}" height="{H}" fill="#f6f3e3"/>
<g transform="{tf}">
<g opacity=".4">{neigh}</g>
<path d="{path_d(rough)}" fill="url(#rough)" stroke="#4d8230" stroke-width=".18" stroke-linejoin="round" filter="url(#lift)"/>
<path d="{path_d(dr[c['fairway']])}" fill="url(#fair)" stroke="#2d6a26" stroke-width=".1"/>
<path d="{path_d(dr[c['fairway']])}" fill="url(#mow)"/>
{tees}
{water}<path d="{path_d(dr[c['green']])}" fill="url(#green)" stroke="#6fbf5a" stroke-width=".22"/>
{bunk}<circle cx="{fb[0]}" cy="{fb[1]}" r=".28" fill="#0a2a10" stroke="#fff" stroke-width=".08"/>
<circle cx="{white[0]}" cy="{white[1]}" r="{6.5/S:.3f}" fill="#fff" stroke="#222" stroke-width="{1.1/S:.3f}"/>
<circle cx="{red[0]}" cy="{red[1]}" r="{6.5/S:.3f}" fill="#ed1c24" stroke="#222" stroke-width="{1.1/S:.3f}"/>
</g>
{tuse}
<g transform="translate({fin(*fb)[0]:.0f} {fin(*fb)[1]:.0f})"><line y2="-40" stroke="#222" stroke-width="1.6"/><path d="M0 -40L22 -32L0 -24Z" fill="#ed1c24" stroke="#7a0b10" stroke-width=".8"/></g>
<g transform="translate({W-22-44} 66)"><rect x="-44" y="-15" width="88" height="30" rx="15" fill="#0f2e4d" stroke="#fff" stroke-width="2"/><text y="6" text-anchor="middle" font-family="Inter, system-ui, sans-serif" font-size="16" font-weight="700" fill="#fff">{c['yards']} yds</text></g>
<circle cx="46" cy="52" r="27" fill="#0f2e4d" stroke="#fff" stroke-width="3"/><text x="46" y="62.8" text-anchor="middle" font-family="Inter, 'Segoe UI', system-ui, sans-serif" font-size="30" font-weight="800" fill="#fff" style="font-variant-numeric:lining-nums">{n}</text>
<g transform="translate({W-22} 40)"><text text-anchor="end" font-family="Inter, system-ui, sans-serif" font-size="17" font-weight="800" fill="#0f2e4d" {halo}>PAR {c['par']}</text>{note}</g>
<text x="{txf+22:.0f}" y="{tyf+5:.0f}" font-family="Inter, system-ui, sans-serif" font-size="11" font-weight="800" letter-spacing="1.5" fill="#0f2e4d" {halo}>TEES</text>
<text x="{gxf+40:.0f}" y="{gyf+6:.0f}" font-family="Inter, system-ui, sans-serif" font-size="11" font-weight="800" letter-spacing="1.5" fill="#0f2e4d" {halo}>GREEN</text>
<rect class="frame-line" x="3" y="3" width="394" height="708" rx="23" fill="none" stroke="#fff" stroke-width="6"/>
</g>
</svg>'''
    full=os.path.join(OUT,f"hole-{n}.svg")
    open(full,"w",encoding="utf-8").write(svg)
    # card version for the Walk the Course stack: no number badge, par, yardage or frame (the card shows those as text)
    keep=[]
    for line in svg.split("\n"):
        if 'fill="#fff">' in line and " yds</text>" in line: continue
        if 'circle cx="46" cy="52"' in line: continue
        if 'class="frame-line"' in line: continue
        if line.startswith('<g transform="translate({W-22} 40)">'.replace("{W-22}",str(W-22))):
            line=(f'<g transform="translate({W-22} 44)">'+(note.replace('y="64"','y="0"') if c["note"] else "")+'</g>')
        keep.append(line)
    cardfile=os.path.join(OUT,f"hole-{n}-card.svg")
    open(cardfile,"w",encoding="utf-8").write("\n".join(keep))
    print(n,len(trees),"trees",os.path.getsize(full)//1024,"KB /",os.path.getsize(cardfile)//1024,"KB card","S=",round(S,2),"rot",round(rot,1))
for n,c in HOLES.items(): build(n,c)




