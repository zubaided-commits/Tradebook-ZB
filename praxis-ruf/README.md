# Praxis-Ruf

Patient calling system for a German medical practice. A doctor calls the next
patient from the waiting room over a speaker without leaving her consultation
room — either with an automatic German voice or her own voice via microphone.

Runs entirely on the local practice network. No dependencies, no internet, no
data written to disk.

- **Setup and daily use (German):** [ANLEITUNG.md](ANLEITUNG.md)
- **Architecture, constraints and open work:** [CLAUDE.md](CLAUDE.md)

## Quick start

```bash
node server.js
```

Then open `http://localhost:8080/praxis.html` on the same machine.

Requires Node.js 18 or newer. Nothing else.
