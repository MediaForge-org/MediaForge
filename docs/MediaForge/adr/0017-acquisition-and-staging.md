# ADR-0017 – Acquisition Center and Staging-first Imports

Status: accepted target

Benutzerbereitgestellte NZB/Torrent/Magnet-Inputs und externe Downloader werden über ein einheitliches Acquisition-Modell integriert. Downloads landen vor finalem Import in Staging/Import Sandbox. Finale Dateischreiboperationen folgen einem expliziten ImportPlan.

MediaForge wird nicht als Piraterie-/Indexer-Suchmaschine konzipiert.
