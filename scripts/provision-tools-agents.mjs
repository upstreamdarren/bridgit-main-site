import { readFile } from "node:fs/promises";

const API_ROOT = "https://api.elevenlabs.io/v1";
const BASE_AGENT_ID = "agent_9001k5y0ara8ej0tqx19x61wcqkq";
const SANDY_AGENT_ID = "agent_0701kw99sgynegx9pgsdmd2kh3dh";
const REPORT_ENDPOINT = "https://bridgit.care/wp-json/bridgit/v1/toolkit-report";
const SECRET_NAME = "bridgit_wordpress_lead_webhook";
const TOOL_NAME = "send_bridgit_toolkit_report";
const COMMISSIONER_NAME = "Bridgit - Digital Commissioning Guide";
const COMMISSIONER_KNOWLEDGE = "Bridgit Knowledge - Digital Commissioning Toolkit v1";
const SANDY_MARKER = "# Emailing the completed co-production plan";

const apiKey = process.env.ELEVENLABS_API_KEY?.trim();
const reportSecret = process.env.BRIDGIT_LEAD_WEBHOOK_SECRET?.trim();
if (!apiKey || !reportSecret) throw new Error("ELEVENLABS_API_KEY and BRIDGIT_LEAD_WEBHOOK_SECRET are required.");

async function request(path, { method = "GET", body } = {}) {
  const response = await fetch(`${API_ROOT}${path}`, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", "xi-api-key": apiKey },
    body: body === undefined ? undefined : JSON.stringify(body)
  });
  const raw = await response.text();
  let result;
  try { result = raw ? JSON.parse(raw) : null; } catch { result = raw; }
  if (!response.ok) throw new Error(`${method} ${path} failed (${response.status}): ${JSON.stringify(result?.detail || result)}`);
  return result;
}

function literal(type, description, extra = {}) { return { type, description, ...extra }; }

async function ensureSecret() {
  const result = await request("/convai/secrets?page_size=100");
  const existing = (result.secrets ?? []).find((secret) => secret.name === SECRET_NAME);
  if (existing) return existing.secret_id;
  return (await request("/convai/secrets", { method: "POST", body: { type: "new", name: SECRET_NAME, value: reportSecret } })).secret_id;
}

async function ensureReportTool(secretId) {
  const result = await request("/convai/tools?page_size=100");
  const existing = (result.tools ?? []).find((tool) => tool.tool_config?.name === TOOL_NAME);
  const toolConfig = {
    type: "webhook",
    name: TOOL_NAME,
    description: "Email a completed co-production plan or digital commissioning recommendation report after the user has heard what will be sent and then supplied an email address to request delivery. Do not ask for a second confirmation. Call immediately once the required name and email are available, then confirm delivery and end the conversation. Never include case data or special-category personal information.",
    response_timeout_secs: 20,
    interruption_mode: "disable_during_tool",
    pre_tool_speech: "off",
    tool_error_handling_mode: "summarized",
    execution_mode: "immediate",
    api_schema: {
      url: REPORT_ENDPOINT,
      method: "POST",
      content_type: "application/json",
      request_headers: { "X-Bridgit-Lead-Secret": { secret_id: secretId } },
      request_body_schema: {
        type: "object", property_kind: "object", description: "A consented plan or recommendation report.",
        required: ["name", "email", "toolkit_type", "context", "recommendation", "actions", "consent"],
        properties: {
          name: literal("string", "Recipient's full name."),
          email: literal("string", "Recipient's email address, supplied after the delivery explanation."),
          organisation: literal(["string", "null"], "Organisation, or null when not provided."),
          toolkit_type: literal("string", "Type of report.", { enum: ["co-production", "digital-commissioning", "digital-readiness", "demand-capacity", "pathway-mapper", "responsible-ai", "partnership-builder", "social-impact"] }),
          context: literal("string", "A concise, non-sensitive summary of the context and intended outcome."),
          recommendation: literal("string", "The tailored plan or recommended route and rationale."),
          actions: literal("string", "Numbered practical next steps."),
          considerations: literal(["string", "null"], "Accessibility, inclusion, safety, data, governance and delivery considerations."),
          options: literal(["string", "null"], "Engagement, solution or supplier options to explore."),
          consent: literal("boolean", "True when the recipient supplies their email after being told what report will be sent.")
        }
      },
      response_body_schema: {
        type: "object", property_kind: "object", required: ["success", "message"],
        properties: { success: literal("boolean", "Whether the email was accepted."), message: literal("string", "Result message.") }
      }
    }
  };
  if (existing) {
    await request(`/convai/tools/${existing.id}`, { method: "PATCH", body: { tool_config: toolConfig } });
    return existing.id;
  }
  return (await request("/convai/tools", { method: "POST", body: { tool_config: toolConfig } })).id;
}

async function ensureCommissionerKnowledge() {
  const text = await readFile(new URL("../docs/elevenlabs/knowledge/digital-commissioning.md", import.meta.url), "utf8");
  const result = await request("/convai/knowledge-base?page_size=100&folders_first=true&sort_by=name&sort_direction=asc");
  const existing = (result.documents ?? []).find((doc) => doc.name === COMMISSIONER_KNOWLEDGE);
  if (existing) {
    await request(`/convai/knowledge-base/${existing.id}`, { method: "PATCH", body: { name: COMMISSIONER_KNOWLEDGE, text } });
    return existing.id;
  }
  return (await request("/convai/knowledge-base/text", { method: "POST", body: { name: COMMISSIONER_KNOWLEDGE, text } })).id;
}

async function updateSandy(reportToolId) {
  const agent = await request(`/convai/agents/${SANDY_AGENT_ID}`);
  const current = agent.conversation_config?.agent?.prompt?.prompt ?? "";
  const addition = `${SANDY_MARKER}\nAfter helping the user shape a plan, offer to email it to them. First summarise the plan in the conversation using Purpose, People to involve, What is open to influence, Engagement method, Accessibility, Questions, Capturing feedback, Decision making, Feedback loop and Measures. Before asking for contact details, explain what will be emailed and that providing an email address after this explanation means they want the plan sent there. Ask for their name and email; use their organisation only if already known. Do not repeat the email, ask for a second confirmation or continue asking questions once the email is supplied. Immediately call send_bridgit_toolkit_report once with toolkit_type=co-production and consent=true. Put the tailored plan in recommendation, numbered actions in actions, access and power considerations in considerations, and engagement methods in options. Never send participant names, case details, health, safeguarding or other sensitive information. Confirm success only when the tool reports it, then end the conversation. On failure, keep the plan on screen, offer contact@bridgit.care and wrap up.`;
  const markerIndex = current.indexOf(SANDY_MARKER);
  const basePrompt = markerIndex >= 0 ? current.slice(0, markerIndex).trimEnd() : current.trimEnd();
  const prompt = `${basePrompt}\n\n${addition}`;
  const toolIds = [...new Set([...(agent.conversation_config?.agent?.prompt?.tool_ids ?? []), reportToolId])];
  await request(`/convai/agents/${SANDY_AGENT_ID}`, {
    method: "PATCH",
    body: {
      tags: [...new Set([...(agent.tags ?? []), "bridgit-tools", "co-production"])],
      version_description: "Adds consented email delivery for completed co-production plans",
      conversation_config: { conversation: { max_duration_seconds: 1200 }, agent: { max_conversation_duration_message: "We have reached the 20 minute conversation limit. Your plan is still available in this chat, and you can start another conversation whenever you are ready.", prompt: { prompt, tool_ids: toolIds, knowledge_base: agent.conversation_config?.agent?.prompt?.knowledge_base ?? [] } } },
      platform_settings: { widget: { disable_banner: true, supports_text_only: true, text_input_enabled: true, transcript_enabled: true, text_contents: { main_label: "Plan co-production with Sandy", start_call: "Start with Sandy", input_placeholder: "Tell Sandy what you want to co-produce..." } } }
    }
  });
  return SANDY_AGENT_ID;
}

async function ensureCommissionerAgent(knowledgeId, reportToolId) {
  const prompt = await readFile(new URL("../docs/elevenlabs/prompts/digital-commissioning.md", import.meta.url), "utf8");
  const agents = (await request("/convai/agents?page_size=100&sort_by=name&sort_direction=asc")).agents ?? [];
  let agent = agents.find((item) => item.name === COMMISSIONER_NAME);
  if (!agent) {
    const created = await request(`/convai/agents/${BASE_AGENT_ID}/duplicate`, { method: "POST", body: { name: COMMISSIONER_NAME } });
    agent = { agent_id: created.agent_id };
  }
  await request(`/convai/agents/${agent.agent_id}`, {
    method: "PATCH",
    body: {
      name: COMMISSIONER_NAME,
      tags: ["bridgit-tools", "commissioning", "public-sector"],
      version_description: "Evidence-led digital commissioning guide with emailed recommendation reports",
      conversation_config: {
        conversation: { max_duration_seconds: 1200 },
        agent: {
          max_conversation_duration_message: "We have reached the 20 minute conversation limit. Your recommendation is still available in this chat, and you can start another conversation whenever you are ready.",
          first_message: "Hello, I'm Bridgit's Digital Commissioning Guide. I can help you turn a community need into a proportionate digital brief, compare routes and suppliers, and email you a recommendation report. What community or service are you thinking about?",
          language: "en",
          prompt: { prompt, tool_ids: [reportToolId], knowledge_base: [{ type: "text", name: COMMISSIONER_KNOWLEDGE, id: knowledgeId, usage_mode: "auto" }] }
        }
      },
      platform_settings: { widget: { variant: "full", placement: "bottom-right", bg_color: "#ffffff", text_color: "#171b2f", btn_color: "#6d2bc3", btn_text_color: "#ffffff", border_color: "#dfd3ee", focus_color: "#c02fb1", show_avatar_when_collapsed: true, disable_banner: true, language_selector: false, supports_text_only: true, text_input_enabled: true, transcript_enabled: true, text_contents: { main_label: "Plan a digital commission", start_call: "Start the toolkit", input_placeholder: "Describe the community need..." } } }
    }
  });
  return agent.agent_id;
}

const secretId = await ensureSecret();
const reportToolId = await ensureReportTool(secretId);
const knowledgeId = await ensureCommissionerKnowledge();
const sandyAgentId = await updateSandy(reportToolId);
const commissionerAgentId = await ensureCommissionerAgent(knowledgeId, reportToolId);
console.log(JSON.stringify({ report_tool_id: reportToolId, sandy_agent_id: sandyAgentId, commissioner_agent_id: commissionerAgentId }, null, 2));
