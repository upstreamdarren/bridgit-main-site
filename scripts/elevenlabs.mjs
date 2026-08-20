const API_ROOT = "https://api.elevenlabs.io/v1";

const apiKey = process.env.ELEVENLABS_API_KEY?.trim();

if (!apiKey) {
  console.error(
    "ELEVENLABS_API_KEY is not available. Create a restricted ElevenLabs key and set it as a user-level environment variable."
  );
  process.exit(1);
}

const [command = "verify", argument] = process.argv.slice(2);

async function request(path) {
  const response = await fetch(`${API_ROOT}${path}`, {
    headers: {
      Accept: "application/json",
      "xi-api-key": apiKey
    }
  });

  const text = await response.text();
  let body;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = text;
  }

  if (!response.ok) {
    const message =
      body?.detail?.message || body?.detail || body?.message || body || response.statusText;
    throw new Error(`ElevenLabs API ${response.status}: ${message}`);
  }

  return body;
}

async function verify() {
  const result = await request("/convai/agents?page_size=1");
  console.log("ElevenLabs API connection verified.");
  console.log(`Agent access available. At least ${result.agents?.length ?? 0} agent record(s) returned.`);
}

async function listAgents() {
  const result = await request("/convai/agents?page_size=100&sort_by=name&sort_direction=asc");
  const agents = result.agents ?? [];

  if (!agents.length) {
    console.log("No agents are visible to this API key.");
    return;
  }

  console.table(
    agents.map(({ agent_id, name, tags, archived }) => ({
      agent_id,
      name,
      tags: (tags ?? []).join(", "),
      archived
    }))
  );
}

async function getAgent(agentId) {
  if (!agentId) throw new Error("Usage: elevenlabs.mjs get-agent <agent_id>");
  const agent = await request(`/convai/agents/${encodeURIComponent(agentId)}`);
  console.log(
    JSON.stringify(
      {
        agent_id: agent.agent_id,
        name: agent.name,
        tags: agent.tags,
        version_id: agent.version_id,
        branch_id: agent.branch_id,
        knowledge_base:
          agent.conversation_config?.agent?.prompt?.knowledge_base ?? [],
        prompt: agent.conversation_config?.agent?.prompt?.prompt,
        tool_ids: agent.conversation_config?.agent?.prompt?.tool_ids ?? [],
        voice_id: agent.conversation_config?.tts?.voice_id,
        language: agent.conversation_config?.agent?.language,
        widget: agent.platform_settings?.widget
      },
      null,
      2
    )
  );
}

async function listKnowledge() {
  const result = await request(
    "/convai/knowledge-base?page_size=100&folders_first=true&sort_by=name&sort_direction=asc"
  );
  const documents = result.documents ?? [];

  if (!documents.length) {
    console.log("No knowledge-base documents are visible to this API key.");
    return;
  }

  console.table(
    documents.map(({ id, name, type, dependent_agents }) => ({
      id,
      name,
      type,
      agents: (dependent_agents ?? []).map((agent) => agent.name).join(", ")
    }))
  );
}

function collectDurationSettings(value, path = "agent", matches = []) {
  if (!value || typeof value !== "object") return matches;

  for (const [key, child] of Object.entries(value)) {
    const childPath = `${path}.${key}`;
    if (/(duration|timeout|limit)/i.test(key)) {
      matches.push({ setting: childPath, value: child });
    }
    if (child && typeof child === "object") collectDurationSettings(child, childPath, matches);
  }

  return matches;
}

async function inspectLimits(agentId) {
  if (!agentId) throw new Error("Usage: elevenlabs.mjs inspect-limits <agent_id>");
  const agent = await request(`/convai/agents/${encodeURIComponent(agentId)}`);
  console.table(collectDurationSettings(agent));
}

const commands = {
  verify,
  "list-agents": listAgents,
  "get-agent": () => getAgent(argument),
  "list-knowledge": listKnowledge,
  "inspect-limits": () => inspectLimits(argument)
};

if (!commands[command]) {
  console.error("Commands: verify, list-agents, get-agent <agent_id>, list-knowledge, inspect-limits <agent_id>");
  process.exit(1);
}

commands[command]().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
