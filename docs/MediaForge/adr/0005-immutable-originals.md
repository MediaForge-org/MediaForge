# ADR-0005: Originaldateien immutable, Verarbeitung erzeugt Artefakte

Status: accepted · Bezug: [architecture/overview.md](../architecture/overview.md), [database/core-schema.md](../database/core-schema.md), Architekturregel 4

## Kontext

MediaForge verarbeitet unwiederbringliche Bestände: gerippte Discs, gekaufte Hörbücher, jahrzehntealte Sammlungen. Verarbeitungsschritte (Tagging, Kapitel-Einbettung, Upscaling, Container-Umwandlung) verändern in vielen bestehenden Werkzeugen die Quelldateien in-place — mit Datenverlust bei Bugs, Abbrüchen oder falschen Parametern.

## Entscheidung

Originaldateien werden von MediaForge niemals verändert, in keiner Betriebsart. Technisch erzwungen: Medienbibliotheken sind in allen Verarbeitungs-Containern read-only gemountet; der Storage-Service besitzt keine Schreiboperationen für Bibliothekspfade. Jede Verarbeitung schreibt in die getrennte Artefakt-Ablage (`/artifacts`), registriert das Ergebnis in der `artifacts`-Tabelle mit Generator, Version, Parametern, Quell-Signatur und Checksum, und schreibt via `<ziel>.partial` + atomarem Rename. Auch Metadaten-Korrekturen an Dateien (Tags, eingebettete Kapitel) erzeugen Kopien oder Sidecars. Einzige Ausnahme: die optionale Inbox (`/inbox`), aus der Import-Workflows Dateien in Bibliotheksstrukturen verschieben dürfen — verschieben, nie transformieren.

## Konsequenzen

* Jede Operation ist risikolos wiederholbar und rückstandsfrei verwerfbar; „Undo" ist Artefakt-Löschung.
* Speicherbedarf steigt (Original + Artefakte); das Artefakt-Housekeeping (superseded/orphaned, Karenzfristen) ist dafür Fundamentbestandteil.
* Der Upscaler kann Original und Verbesserung ehrlich nebeneinanderstellen (A/B-Vergleich), weil das Original garantiert unberührt ist.
* Benutzer, die In-Place-Tagging wollen, müssen es außerhalb von MediaForge tun — bewusste Grenze, dokumentiert im Benutzerhandbuch.

## Erwogene Alternativen

In-Place mit Backup-Kopie (verdoppelt Risiko-Fenster statt es zu schließen; Backups verwaisen), Copy-on-Write auf Dateisystemebene (ZFS/Btrfs-Snapshots — wertvoll, aber nicht vorauszusetzen; MediaForge darf sich nicht auf Dateisystem-Features verlassen), Schreibrechte mit „Vorsicht" (Konvention ohne Enforcement ist bei unwiederbringlichen Daten inakzeptabel).
