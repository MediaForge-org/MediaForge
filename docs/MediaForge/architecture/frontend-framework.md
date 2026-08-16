# Web Frontend Framework Decision

Status: **verbindliche Zielentscheidung**

## Entscheidung

MediaForge verwendet langfristig **React 19 + TypeScript + React Router im Framework Mode + Vite**. Inertia ist nur Migrationsbestand und wird entfernt. Next.js ist bewusst **nicht** die Standard-Web-Runtime.

## Warum Framework Mode statt nur nacktem React Router

Framework Mode liefert die Struktur, die MediaForge langfristig benötigt, ohne eine zweite Full-Stack-Serverarchitektur neben Laravel einzuführen:

- typisierte Route Modules;
- Loader/Actions und klar definierte Data Boundaries;
- intelligentes Route Code Splitting;
- Error/Loading Boundaries;
- SPA als Default-Betriebsart;
- optionales SSR/Pre-Rendering, falls einzelne öffentliche oder statische Oberflächen es später benötigen;
- weiterhin ein klarer MediaForge API-v1-Vertrag als Servergrenze.

## Warum nicht Next.js als Standard

Next.js wäre technisch möglich, würde aber zusätzlich zu Laravel eine zweite serverseitige Full-Stack-Schicht mit eigener Node-Server-Runtime, Rendering-/Caching-Semantik und Server-Komponenten einführen. MediaForge ist primär eine authentifizierte, selbstgehostete Medienanwendung. Auth, Fachlogik, Jobs, PostgreSQL, Provenienz, Engine-Orchestrierung und API gehören bereits dem Laravel Control Plane.

Ziel ist daher:

```text
Browser / Clients
      |
React Router Framework Mode
      |
MediaForge API v1
      |
Laravel Control Plane
```

Nicht:

```text
Browser -> Next Full Stack -> Laravel Full Stack -> Engines
```

## SSR

SSR ist **kein Zwang**. Die normale MediaForge-App läuft als SPA. Falls später öffentliche Share-Seiten oder andere konkrete Oberflächen SSR benötigen, kann dies gezielt aktiviert werden, ohne die gesamte Fachlogik in JavaScript zu duplizieren.

## Migrationsregel

Neue Ziel-UI darf keine neue Inertia-Abhängigkeit einführen. Die Migration erfolgt strangler-artig und hält den jeweils letzten grünen Stand benutzbar.
