# Adult Performer 3D Reconstruction and Tattoo Coverage

Status: **P0 Datenmodell/Contracts, P2 schwere Analyse**

Referenzscreens: `43`, `44`, `48`–`54`, `61`–`67` in `ui-ux/reference-expanded/`.

## Ziel

Für weibliche Adult-Performer kann MediaForge optional aus mehreren lokalen Scenes ein individuelles kanonisches 3D-Körpermodell rekonstruieren. Das Modell dient insbesondere einer reproduzierbaren Tattoo-Coverage-Berechnung und regionalen Analyse.

Die Funktion ist vollständig optional. Ohne heruntergeladene AI-Modelle und ohne 3D-Capability bleibt MediaForge normal benutzbar.

## Rekonstruktionspipeline

```text
lokale Scenes
 -> Evidence Selection
 -> Person Tracking / Pose / Camera
 -> Multi-view Body Shape Fusion
 -> Canonical Body Mesh
 -> Surface/Region Map
 -> Tattoo Segmentation
 -> Tattoo Projection
 -> Confidence Fusion
 -> immutable ReconstructionRevision
```

Kein einzelnes Frame darf als vollständiger Körperscan behandelt werden.

## Kanonisches Modell und Revisionen

```text
PerformerAnalysisProfile
  ReconstructionRevision[]
    provider
    provider_version
    mesh_asset_id
    texture_asset_id nullable
    region_map_version
    evidence_bundle_ids[]
    observed_surface_pct
    estimated_surface_pct
    unknown_surface_pct
    confidence
```

Neue Evidence erzeugt eine neue Revision. Alte Revisionen bleiben vergleichbar/rollbackfähig, solange Retention sie nicht bewusst entfernt.

## Tattoo Coverage

Der wichtigste Wert ist **Fläche**, nicht Tattoo-Anzahl.

```text
overall_tattoo_coverage_pct = tattooed_body_surface / total_body_surface
```

Der Nenner ist immer die geschätzte **gesamte Körperoberfläche**, nicht nur aktuell sichtbare Haut. Unsichtbare Regionen werden nicht als tattoo-frei gewertet.

MediaForge speichert:

- `confirmed_min_pct`;
- `estimated_pct`;
- `possible_max_pct`;
- `observed_surface_pct`;
- `estimated_surface_pct`;
- `unknown_surface_pct`;
- Confidence/Verification.

## Coverage Classes

MediaForge-Produktklassifikation:

| Class | Gesamt-Coverage |
|---|---:|
| `none` | 0 % |
| `minimal` | >0–5 % |
| `light` | >5–15 % |
| `moderate` | >15–35 % |
| `heavy` | >35–55 % |
| `very_heavy` | >55–75 % |
| `near_body_suit` | >75–90 % |
| `body_suit` | >90–100 % |

`body_suit` soll zusätzlich eine großflächige Verteilung über mehrere Hauptregionen voraussetzen; der Prozentwert bleibt die primäre Kennzahl.

## Versionierte Anatomy Ontology

Keine freien Strings. Beispiele stabiler IDs:

```text
body.head.forehead
body.head.cheek.left
body.neck.front
body.arm.upper.left
body.arm.forearm.right
body.hand.palm.left
body.hand.fingers.right
body.torso.chest.upper
body.torso.breast.left
body.torso.breast.right
body.torso.abdomen.lower
body.back.upper
body.back.lower
body.pelvis.hip.left
body.pelvis.buttock.left
body.pelvis.buttock.right
body.leg.thigh.front.left
body.leg.thigh.back.left
body.leg.thigh.inner.left
body.leg.thigh.outer.left
body.leg.knee.left
body.leg.shin.left
body.leg.calf.left
body.foot.top.left
body.foot.sole.left
```

Alle bilateralen Regionen besitzen links/rechts. Parent-Regionen aggregieren Children.

## Region Coverage

Jede Region speichert:

```text
surface_area
confirmed_tattoo_area
estimated_tattoo_area
coverage_min_pct
coverage_estimated_pct
coverage_max_pct
observed_pct
confidence
```

Die Gesamtabdeckung ist ein flächengewichtetes Mesh-Ergebnis und **kein Durchschnitt der Regionsprozente**.

## Körperform und Kalibrierung

Die Oberfläche wird aus dem rekonstruierten 3D-Mesh abgeleitet. Verlässliche Source-Facts wie Größe, Gewicht und veröffentlichte Körpermaße können die Skalierung kalibrieren. Brust-/Hüft-/Gesäß-/Oberschenkelgeometrie wird primär geometrisch aus dem Mesh berücksichtigt statt durch grobe pauschale Prozenttabellen.

Unzuverlässige oder nur visuell geschätzte Körpermaße dürfen nicht als verifizierte Fakten ausgegeben werden.

## Female-focused Detail

Die detaillierte Coverage-Pipeline ist für weibliche Adult-Performer priorisiert. Für männliche Performer genügt standardmäßig eine gröbere Tattoo-Klassifikation. Das technische Modell bleibt erweiterbar.

## Viewer

Der Adult-3D-Viewer unterstützt:

- rotate/zoom/pan;
- front/back/left/right;
- Tattoo Heatmap;
- Region Map;
- Confidence/Observed/Unknown Overlay;
- Evidence Jump;
- Revision Compare.

Im entsperrten Adult-Bereich kann die Darstellung je nach Nutzerpräferenz neutral/bekleidet oder als vollständiges Körpermodell erfolgen. Referenzbilder in der Doku bleiben neutral.

## Privacy

Meshes, Texturen, Tattoo Masks, Body Measurements, Evidence und Previews sind Adult-private Assets und dürfen bei gesperrtem Adult Mode nicht über Search, Cache, Notifications, Logs oder Preload leaken.
