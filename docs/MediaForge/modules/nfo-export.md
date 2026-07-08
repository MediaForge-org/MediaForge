# NFO Export

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Dieses kleine Modul ist im [Modul-Cookbook](../developer-handbook/module-cookbook.md) als durchgerechnetes Beispiel spezifiziert.

## Motivation

NFO-lesende Werkzeuge wie Kodi sollen kuratierte MediaForge-Metadaten konsumieren können, ohne dass MediaForge Originalmedien oder Sidecar-Dateien neben Originalen verändert. Der NFO Export erzeugt deshalb ausschließlich Artefakte in der Artefakt-Ablage.

## Architekturentscheidung

Der Export ist ein lokales Enhancement-Artefakt. Quelle ist der aktuelle MediaForge-Enhancement-Katalog inklusive Metadaten-Governance, Herkunft und manuellen Overrides. Ziel ist ein `artifact_type='nfo_export'`, niemals eine In-Place-Datei neben Originalmedien.

## Integration

Das Modul ergänzt [Metadata Enrichment](enrichment.md), [Backup/Restore](backup-restore.md) und das [Plugin SDK](../developer-handbook/plugin-sdk.md). Jellyfin und Audiobookshelf werden dadurch nicht ersetzt; der Export ist ein optionaler Kompatibilitätsweg für lokale Drittwerkzeuge.
