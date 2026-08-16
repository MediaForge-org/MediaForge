# Green Commit Development Workflow

Status: **verbindlicher MediaForge-Entwicklungsworkflow**

## Grundprinzip

MediaForge wird standardmäßig direkt auf `main` entwickelt. `main` ist eine Folge kleiner, funktionierender und getesteter Inkremente.

Branches sind kein Zwang pro Prompt. Sie dürfen für außergewöhnlich riskante/experimentelle Arbeit genutzt werden, sind aber nicht der Standard.

## Arbeitspaket

```text
last green commit
 -> Claude implements one scoped package
 -> focused tests
 -> relevant full gates
 -> if green: logical commit(s)
 -> push main
 -> GitHub Actions must be green
 -> next package
```

## Wenn etwas kaputtgeht

1. Zuerst innerhalb des aktuellen Arbeitspakets sauber fixen.
2. Wenn der Ansatz scheitert, nur die Änderungen dieses Arbeitspakets zurücknehmen.
3. Vor `git restore`, `reset` oder `clean` immer `git status`/`git diff` prüfen.
4. `git reset --hard` nur, wenn eindeutig keine fremden/uncommitteten Nutzeränderungen verloren gehen können.
5. Bereits gepushte Commits werden normalerweise mit `git revert` korrigiert statt History umzuschreiben.
6. Kein bekannter kaputter Zustand wird committed oder als abgeschlossen markiert.

## Commit-Schnitt

Nicht ein Commit pro Prompt und nicht ein Riesensammelcommit. Große Arbeitspakete werden fachlich geteilt, z. B. Contract/DB, Backend, Frontend, Tests/Docs. Jeder Commit soll sinnvoll verständlich sein; der Endstand des Pakets muss grün sein.

## GitHub Actions

Nach Push wird nicht mit dem nächsten Paket begonnen, solange Required CI nicht grün ist. Lokale Gates ersetzen GitHub Actions nicht.
