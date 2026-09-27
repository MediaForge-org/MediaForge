#!/usr/bin/env python3
import json,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
D=json.loads((ROOT/'docs/MediaForge/phases/PHASE_CATALOG.json').read_text())
def main():
 if len(sys.argv)!=2:print('usage: python3 tools/phases/show_phase.py M2.1',file=sys.stderr);return 2
 t=sys.argv[1].upper()
 for m in D['milestones']:
  if t==m['id']:
   print(f"# {m['id']} — {m['title']}\n")
   for u in m['units']:print(f"- {u['id']} — {u['title']}"+(' [CURRENT]' if u['status']=='current' else '')+(' [GATE]' if u.get('gate') else ''))
   return 0
  for u in m['units']:
   if t==u['id']:
    print(f"# {u['id']} — {u['title']}\n")
    print(f"Milestone: {m['id']} — {m['title']}")
    print(f"Status: {u['status']}\nComplexity: {u['complexity']}\nRecommended bundle max: {u['recommended_bundle_max']}\nGate: {'yes' if u.get('gate') else 'no'}\nDepends on: {', '.join(u['depends_on']) if u['depends_on'] else 'none'}\n")
    print('## Goal\n'+u['goal']+'\n')
    print('## Required reads\n- docs/MediaForge/phases/EXECUTION_RULES.md\n- docs/MediaForge/phases/TEST_STRATEGY.md')
    for x in m['required_reads']:print('- '+x)
    print('\n## Source hints\nUse graphify-out/GRAPH_REPORT.md first when present, then only relevant neighborhoods:')
    for x in m['source_hints']:print('- '+x)
    print('\n## Required test profiles')
    for p in u['test_profiles']:
     print('\n### '+p)
     for rule in D['test_profiles'][p]:print('- '+rule)
    print('\n## Done criteria')
    for x in u['done_criteria']:print('- [ ] '+x)
    print('\nDo not automatically continue unless a bundle was authorized. No commit/push/tag/release unless explicitly authorized.')
    return 0
 print('unknown phase: '+t,file=sys.stderr);return 1
if __name__=='__main__':raise SystemExit(main())
