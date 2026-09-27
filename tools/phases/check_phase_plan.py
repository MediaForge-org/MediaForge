#!/usr/bin/env python3
import json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
CAT=ROOT/'docs/MediaForge/phases/PHASE_CATALOG.json'
UR=re.compile(r'^M([1-9][0-9]*)\.([1-9][0-9]*)(?:\.([1-9][0-9]*))?$')
MR=re.compile(r'^M([1-9][0-9]*)$')
def validate(d):
 e=[]
 if not isinstance(d,dict): return ['catalog must be object']
 ms=d.get('milestones');
 if not isinstance(ms,list) or not ms: return ['milestones must be non-empty array']
 seenm=set();seenu=set();allunits=[];tracks=set();statuses={'done','current','planned','blocked'}
 for mi,m in enumerate(ms):
  mid=m.get('id') if isinstance(m,dict) else None
  if not isinstance(mid,str) or not MR.fullmatch(mid): e.append(f'milestone[{mi}] invalid id {mid!r}'); continue
  if mid in seenm:e.append(f'duplicate milestone {mid}')
  seenm.add(mid)
  if mid!=f'M{mi+1}':e.append(f'expected M{mi+1}, got {mid}')
  tr=m.get('legacy_tracks',[])
  if not isinstance(tr,list) or not all(isinstance(x,int) and 1<=x<=36 for x in tr):e.append(f'{mid}: invalid legacy_tracks')
  else:tracks.update(tr)
  if m.get('status') not in statuses:e.append(f'{mid}: invalid status')
  us=m.get('units')
  if not isinstance(us,list) or not us:e.append(f'{mid}: no units');continue
  gates=0
  for ui,u in enumerate(us):
   uid=u.get('id') if isinstance(u,dict) else None
   if not isinstance(uid,str) or not UR.fullmatch(uid):e.append(f'{mid}.units[{ui}] invalid id {uid!r}');continue
   if uid!=f'{mid}.{ui+1}':e.append(f'{mid}: expected {mid}.{ui+1}, got {uid}')
   if uid in seenu:e.append(f'duplicate unit {uid}')
   seenu.add(uid);allunits.append(u)
   if u.get('status') not in statuses:e.append(f'{uid}: invalid status')
   if u.get('complexity') not in {'simple','medium','hard','critical'}:e.append(f'{uid}: invalid complexity')
   if not isinstance(u.get('recommended_bundle_max'),int) or u['recommended_bundle_max']<1:e.append(f'{uid}: invalid bundle max')
   if not isinstance(u.get('test_profiles'),list) or not u['test_profiles']:e.append(f'{uid}: tests required')
   if not isinstance(u.get('done_criteria'),list) or len(u['done_criteria'])<4:e.append(f'{uid}: done criteria too small')
   if u.get('gate') is True:gates+=1
  if gates!=1 or us[-1].get('gate') is not True:e.append(f'{mid}: exactly final unit must be gate')
 ids={u['id'] for u in allunits if isinstance(u.get('id'),str)};prev=None
 for u in allunits:
  uid=u['id'];deps=u.get('depends_on')
  if not isinstance(deps,list) or not all(isinstance(x,str) for x in deps):e.append(f'{uid}: bad deps');continue
  for dep in deps:
   if dep not in ids:e.append(f'{uid}: missing dependency {dep}')
   if dep.startswith('P'):e.append(f'{uid}: legacy P dependency')
  if prev is None and deps:e.append(f'{uid}: first unit must have no dep')
  if prev is not None and deps!=[prev]:e.append(f'{uid}: expected dependency {prev}')
  prev=uid
 if tracks!=set(range(1,37)):e.append(f'legacy track coverage wrong missing={sorted(set(range(1,37))-tracks)}')
 cur=d.get('current_unit'); currents=[u['id'] for u in allunits if u.get('status')=='current']
 if cur not in ids:e.append('current_unit missing')
 if currents!=[cur]:e.append(f'current status mismatch {currents!r} vs {cur!r}')
 defined=d.get('test_profiles',{})
 for u in allunits:
  for p in u.get('test_profiles',[]):
   if p not in defined:e.append(f"{u['id']}: unknown test profile {p}")
 return e
def main():
 try:d=json.loads(CAT.read_text(encoding='utf-8'))
 except Exception as x:print(f'ERROR: {x}');return 1
 e=validate(d)
 if e:
  print(f'Phase plan INVALID ({len(e)}):');[print('- '+x) for x in e];return 1
 units=[u for m in d['milestones'] for u in m['units']]
 print(f"{len(d['milestones'])} milestones, {len(units)} execution units checked.")
 print(f"Current unit: {d['current_unit']}")
 print('Legacy track coverage: 36/36.')
 print('Result: clean.')
 return 0
if __name__=='__main__':raise SystemExit(main())
