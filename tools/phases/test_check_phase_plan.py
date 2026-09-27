import copy,importlib.util,json,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
s=importlib.util.spec_from_file_location('c',ROOT/'tools/phases/check_phase_plan.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
D=json.loads((ROOT/'docs/MediaForge/phases/PHASE_CATALOG.json').read_text())
class T(unittest.TestCase):
 def test_real(self):self.assertEqual(m.validate(D),[])
 def test_duplicate(self):
  d=copy.deepcopy(D);d['milestones'][0]['units'][1]['id']=d['milestones'][0]['units'][0]['id'];self.assertTrue(m.validate(d))
 def test_missing_dep(self):
  d=copy.deepcopy(D);d['milestones'][1]['units'][0]['depends_on']=['M99.99'];self.assertTrue(any('missing dependency' in x for x in m.validate(d)))
 def test_legacy_dep(self):
  d=copy.deepcopy(D);d['milestones'][1]['units'][0]['depends_on']=['P0012'];self.assertTrue(any('legacy P' in x for x in m.validate(d)))
 def test_current(self):
  d=copy.deepcopy(D);d['current_unit']='M2.2';self.assertTrue(any('current status' in x for x in m.validate(d)))
 def test_gate(self):
  d=copy.deepcopy(D);d['milestones'][2]['units'][-1]['gate']=False;self.assertTrue(any('gate' in x for x in m.validate(d)))
 def test_tracks(self):
  d=copy.deepcopy(D)
  for x in d['milestones']:x['legacy_tracks']=[t for t in x['legacy_tracks'] if t!=36]
  self.assertTrue(any('coverage' in x for x in m.validate(d)))
 def test_profile(self):
  d=copy.deepcopy(D);d['milestones'][1]['units'][0]['test_profiles'].append('magic');self.assertTrue(any('unknown test profile' in x for x in m.validate(d)))
if __name__=='__main__':unittest.main()
