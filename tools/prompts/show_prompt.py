#!/usr/bin/env python3
import json,sys,pathlib
root=pathlib.Path(__file__).resolve().parents[2]
cat=json.loads((root/'docs/MediaForge/prompts/PROMPT_CATALOG.json').read_text())
if len(sys.argv)!=2:
 print("usage: show_prompt.py P0001",file=sys.stderr); raise SystemExit(2)
pid=sys.argv[1].upper()
for p in cat:
 if p['id']==pid:
  f=root/p['file']; print(f); print(); print(f.read_text()); break
else:
 print(f"unknown prompt {pid}",file=sys.stderr); raise SystemExit(1)
