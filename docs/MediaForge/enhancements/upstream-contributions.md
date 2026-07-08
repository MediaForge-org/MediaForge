# Upstream-Strategie

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

MediaForge bleibt eine lokale Enhancement Suite. Funktionen, die allgemein für Jellyfin oder Audiobookshelf nützlich sind, können später upstream-freundlich umgesetzt werden.

## Wege

- Plugin oder Extension für Jellyfin
- Plugin oder Extension für Audiobookshelf, soweit vom Projekt unterstützt
- Pull Request an Ursprungssysteme
- dokumentierte Integrationsstrategie
- optionaler Fork nur als letzter, klar markierter Sonderweg

MediaForge-spezifische Funktionen bleiben in MediaForge. Eine direkte Quellcode-Verschmelzung der Ursprungssysteme ist kein Ziel.

## Entscheidungskriterien

Eine Funktion ist upstream-tauglich, wenn sie allgemein für Jellyfin oder Audiobookshelf nützlich ist, keine MediaForge-spezifischen Datenmodelle voraussetzt und mit den Wartungsregeln des Ursprungssystems vereinbar ist. MediaForge-spezifisch bleibt sie, wenn sie Unified Dashboard, Metadata Governance, Adult Enhancement, Rule/Workflow Engine oder MediaForge-Audit voraussetzt.

## Querverweise

Kompatibilitätsregeln stehen in [compatibility-policy.md](compatibility-policy.md), Plugin-Entwicklung in [plugin-development.md](plugin-development.md), Connector-Grenzen in [../connectors/connector-sdk.md](../connectors/connector-sdk.md).
