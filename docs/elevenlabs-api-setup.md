# ElevenLabs API setup for Bridgit

## What the key will be used for

The local management script will use the ElevenLabs API to inspect and, once separately approved, create or update:

- one specialist agent for each landing page;
- each agent's prompt, voice and first message;
- offer-specific knowledge-base documents;
- widget appearance, avatar and display text;
- domain allowlists and other agent security settings.

The API key must never be placed in the website, browser JavaScript, an Astro page, Cloudflare Pages or GitHub. Public widgets use their agent IDs in the page embed; they do not need the management API key.

## Create the key

1. Sign in to ElevenLabs.
2. Open **Developers → API Keys**.
3. Create a new restricted key named **Bridgit Codex Agent Manager**.
4. Give it only the permissions needed for **ElevenLabs Agents / Conversational AI** and **Knowledge Base** management. Add read access to voices if we will select voices through the API. Do not enable unrelated products.
5. Set a modest credit limit and, for initial development, an expiry such as 30 days.
6. Copy the key when it is displayed. ElevenLabs only shows the full value once.

## Store it on Windows without putting it in chat or Git

Open a PowerShell window and run:

```powershell
$bridgitElevenLabsKey = Read-Host "Paste the ElevenLabs API key" -AsSecureString
$bridgitKeyPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($bridgitElevenLabsKey)
try {
  $bridgitPlainKey = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bridgitKeyPointer)
  [Environment]::SetEnvironmentVariable("ELEVENLABS_API_KEY", $bridgitPlainKey, "User")
} finally {
  [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bridgitKeyPointer)
  $bridgitPlainKey = $null
}
```

The prompt hides the key while it is pasted. Close that PowerShell window afterwards and tell Codex only that the key has been stored—never paste the key into chat.

## Read-only verification

The first API call should always be the read-only verifier:

```powershell
$env:ELEVENLABS_API_KEY = [Environment]::GetEnvironmentVariable("ELEVENLABS_API_KEY", "User")
& "C:\Users\darre\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" scripts\elevenlabs.mjs verify
```

Other read-only commands:

```powershell
& "C:\Users\darre\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" scripts\elevenlabs.mjs list-agents
& "C:\Users\darre\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" scripts\elevenlabs.mjs get-agent agent_9001k5y0ara8ej0tqx19x61wcqkq
& "C:\Users\darre\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" scripts\elevenlabs.mjs list-knowledge
```

The script deliberately contains no write commands yet. Those will be added only after the key is verified and the existing Bridgit agent configuration has been safely inspected.
