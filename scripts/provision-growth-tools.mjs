const API_ROOT = "https://api.elevenlabs.io/v1";
const BASE_AGENT_ID = "agent_9001k5y0ara8ej0tqx19x61wcqkq";
const REPORT_ENDPOINT = "https://bridgit.care/wp-json/bridgit/v1/toolkit-report";
const SECRET_NAME = "bridgit_wordpress_lead_webhook";
const TOOL_NAME = "send_bridgit_toolkit_report";

const apiKey = process.env.ELEVENLABS_API_KEY?.trim();
const reportSecret = process.env.BRIDGIT_LEAD_WEBHOOK_SECRET?.trim();
if (!apiKey || !reportSecret) throw new Error("ELEVENLABS_API_KEY and BRIDGIT_LEAD_WEBHOOK_SECRET are required.");
console.log("Preparing Bridgit growth tool agents...");

const references = {
  digital: `Use official UK Government guidance including the GOV.UK Service Standard, Technology Code of Practice, Data and AI Ethics Framework, AI Playbook and ICO AI guidance. Be proportionate. Start with user need, accessibility, data protection, service operations and governance, not a preferred product.`,
  pathways: `Use service-design and co-production practice. Look for repeat contacts, failed handovers, inaccessible information, unclear ownership and gaps between voluntary, community, public and clinical services. Do not ask for identifiable case details.`,
  impact: `Social Impact Advisor knowledge. This tool is delivered in partnership with Social Enterprise UK (SEUK). It should help organisations understand, measure, manage and improve their social impact. Use SEUK's emphasis on measuring and communicating impact to evidence change and support future funding. Use Social Value International's Principles of Social Value: involve people affected; understand change; value what matters; only include material information; do not overclaim; be transparent; verify results; and respond to what is learned. For UK public procurement, know the PPN 002 Social Value Model applies to relevant and proportionate central-government procurements under the Procurement Act, including social-value commitments in contract management. Do not claim an organisation has created impact without evidence. Help them make a practical, proportionate measurement plan: mission and intended change; people affected; theory of change; indicators; baseline; evidence; review cadence; ownership; learning; transparent communication. Explain that it is not a verified SROI, assurance statement or financial valuation.`
};

const tools = [
  {
    key: "digital-readiness",
    name: "Bridgit - Digital Readiness Review",
    knowledgeName: "Bridgit Knowledge - Digital Readiness Review v1",
    firstMessage: "Hello, I’m Bridgit’s Digital Readiness Guide. I can help you understand what is ready now, what needs strengthening, and what a sensible first step could be. What service or organisation are you thinking about?",
    prompt: `You are Bridgit's Digital Readiness Guide. Help leaders in public services, charities and purpose-led organisations assess readiness for digital and AI support. Work in plain English. Ask one clear question at a time. Cover people, user need, current journey, content, data, accessibility, governance, procurement, supplier capability, measures and a proportionate next step. Avoid technology hype and do not automatically recommend Bridgit. Suggest improving existing processes or systems where that is best. ${references.digital}`
  },
  {
    key: "demand-capacity",
    name: "Bridgit - Demand and Capacity Planner",
    knowledgeName: "Bridgit Knowledge - Demand and Capacity Planner v1",
    firstMessage: "Hello, I’m Bridgit’s Demand and Capacity Planner. Tell me where demand is growing or where your team is spending too much time on repeat work, and we will shape a practical response.",
    prompt: `You are Bridgit's Demand and Capacity Planner. Help service leaders understand demand, repeat contact, preventable friction, staff capacity and practical action. Ask about the people affected, top contact reasons, demand pattern, queues, existing support, avoidable repeat work, risk and measures. Help them create a small set of tests that extend human support rather than replace people. Never make clinical, safeguarding, legal or workforce decisions. ${references.digital}`
  },
  {
    key: "pathway-mapper",
    name: "Bridgit - Pathway Mapper",
    knowledgeName: "Bridgit Knowledge - Pathway Mapper v1",
    firstMessage: "Hello, I’m Bridgit’s Pathway Mapper. I can help you map how people currently find help, where they fall through gaps, and what to improve first. Which journey are you looking at?",
    prompt: `You are Bridgit's Pathway Mapper. Help organisations map a real support journey from first question to the right human help. Identify unclear routes, repeated explanations, broken referrals, waits, inaccessible information, missing partners and weak feedback loops. Capture what needs to be shared, who owns each step, consent and safe handover requirements. Never request identifiable case details. ${references.pathways}`
  },
  {
    key: "responsible-ai",
    name: "Bridgit - Responsible AI Action Plan",
    knowledgeName: "Bridgit Knowledge - Responsible AI Action Plan v1",
    firstMessage: "Hello, I’m Bridgit’s Responsible AI Guide. I can help you turn a promising AI idea into a safe, human-centred action plan. What are you considering?",
    prompt: `You are Bridgit's Responsible AI Guide. Help organisations make responsible, proportionate decisions about AI. Establish the outcome, people affected, potential harms, human oversight, data quality and lawful basis, accessibility, security, transparency, supplier due diligence, testing, monitoring, ownership and escalation. Do not provide legal advice or approve a system as compliant. Make uncertainty explicit and recommend appropriate specialist review. ${references.digital}`
  },
  {
    key: "partnership-builder",
    name: "Bridgit - Partnership Builder",
    knowledgeName: "Bridgit Knowledge - Partnership Builder v1",
    firstMessage: "Hello, I’m Bridgit’s Partnership Builder. I can help you turn a shared ambition into a clear, practical partnership plan. What change are you hoping to make together?",
    prompt: `You are Bridgit's Partnership Builder. Help charities, social enterprises, councils, funders and businesses shape partnerships that improve support for people. Explore shared purpose, people affected, assets each partner brings, referral and signposting routes, roles, information governance, resourcing, success measures and a realistic first pilot. Be clear that good partnerships protect each organisation's identity and local expertise. ${references.pathways}`
  },
  {
    key: "social-impact",
    name: "Bridgit - Social Impact Advisor",
    knowledgeName: "Bridgit Knowledge - Social Impact Advisor v1",
    firstMessage: "Hello, I’m Bridgit’s Social Impact Advisor, delivered in partnership with SEUK. I can help you understand the change you are making, choose useful measures and build a practical plan to increase impact. Where would you like to start?",
    prompt: `You are Bridgit's Social Impact Advisor, delivered in partnership with Social Enterprise UK. You help social enterprises, charities, purpose-led businesses, funders and public services understand and improve their social impact. Be encouraging, practical and rigorous. Ask one question at a time. Start with mission, people affected and the change they care about. Then build a proportionate theory of change, measurement plan, evidence approach and review cycle. Help users avoid vanity metrics and overclaiming. Suggest a small number of meaningful output and outcome indicators, a baseline, a method to hear from people affected, an owner, an evidence rhythm and how the learning will improve delivery. Explain the difference between monitoring, evaluation, impact measurement, social value and financial valuation. Never invent results, promise a Social Return on Investment figure or present the output as assurance. ${references.impact}`
  }
];

async function request(path, { method = "GET", body } = {}) {
  const response = await fetch(`${API_ROOT}${path}`, { method, headers: { Accept: "application/json", "Content-Type": "application/json", "xi-api-key": apiKey }, body: body === undefined ? undefined : JSON.stringify(body) });
  const raw = await response.text();
  let result;
  try { result = raw ? JSON.parse(raw) : null; } catch { result = raw; }
  if (!response.ok) throw new Error(`${method} ${path} failed (${response.status}): ${JSON.stringify(result?.detail || result)}`);
  return result;
}

async function ensureSecret() {
  const result = await request("/convai/secrets?page_size=100");
  const existing = (result.secrets ?? []).find((secret) => secret.name === SECRET_NAME);
  if (existing) return existing.secret_id;
  return (await request("/convai/secrets", { method: "POST", body: { type: "new", name: SECRET_NAME, value: reportSecret } })).secret_id;
}

async function ensureReportTool(secretId) {
  const result = await request("/convai/tools?page_size=100");
  const existing = (result.tools ?? []).find((tool) => tool.tool_config?.name === TOOL_NAME);
  const reportTypes = ["co-production", "digital-commissioning", ...tools.map((tool) => tool.key)];
  const toolConfig = {
    type: "webhook", name: TOOL_NAME,
    description: "Email a completed Bridgit tool report after the user has heard what will be sent and then supplied an email address to request delivery. Do not ask for a second confirmation. Call immediately once the required name and email are available, then confirm delivery and end the conversation. Never include special-category personal data, safeguarding details or identifiable case information.",
    response_timeout_secs: 20, interruption_mode: "disable_during_tool", pre_tool_speech: "off", tool_error_handling_mode: "summarized", execution_mode: "immediate",
    api_schema: {
      url: REPORT_ENDPOINT, method: "POST", content_type: "application/json", request_headers: { "X-Bridgit-Lead-Secret": { secret_id: secretId } },
      request_body_schema: { type: "object", property_kind: "object", required: ["name", "email", "toolkit_type", "context", "recommendation", "actions", "consent"], properties: {
        name: { type: "string", description: "Recipient's full name." }, email: { type: "string", description: "Recipient's email address, supplied after the delivery explanation." }, organisation: { type: ["string", "null"], description: "Organisation, or null." }, toolkit_type: { type: "string", enum: reportTypes, description: "Tool creating the report." }, context: { type: "string", description: "A concise non-sensitive summary of the context." }, recommendation: { type: "string", description: "The tailored recommendation or plan." }, actions: { type: "string", description: "Numbered practical next steps." }, considerations: { type: ["string", "null"], description: "Inclusion, safety, governance and delivery considerations." }, options: { type: ["string", "null"], description: "Options, partners, measures or resources to explore." }, consent: { type: "boolean", description: "True when the recipient supplies their email after being told what report will be sent." }
      } },
      response_body_schema: { type: "object", property_kind: "object", required: ["success", "message"], properties: { success: { type: "boolean", description: "Whether the email was accepted." }, message: { type: "string", description: "Result message." } } }
    }
  };
  if (existing) { await request(`/convai/tools/${existing.id}`, { method: "PATCH", body: { tool_config: toolConfig } }); return existing.id; }
  return (await request("/convai/tools", { method: "POST", body: { tool_config: toolConfig } })).id;
}

async function ensureKnowledge(tool) {
  const result = await request("/convai/knowledge-base?page_size=100&folders_first=true&sort_by=name&sort_direction=asc");
  const existing = (result.documents ?? []).find((document) => document.name === tool.knowledgeName);
  if (existing) { await request(`/convai/knowledge-base/${existing.id}`, { method: "PATCH", body: { name: tool.knowledgeName, text: tool.prompt } }); return existing.id; }
  return (await request("/convai/knowledge-base/text", { method: "POST", body: { name: tool.knowledgeName, text: tool.prompt } })).id;
}

async function ensureAgent(tool, knowledgeId, reportToolId) {
  const agents = (await request("/convai/agents?page_size=100&sort_by=name&sort_direction=asc")).agents ?? [];
  let agent = agents.find((item) => item.name === tool.name);
  if (!agent) {
    const created = await request(`/convai/agents/${BASE_AGENT_ID}/duplicate`, { method: "POST", body: { name: tool.name } });
    agent = { agent_id: created.agent_id };
  }
  await request(`/convai/agents/${agent.agent_id}`, { method: "PATCH", body: {
    name: tool.name, tags: ["bridgit-tools", tool.key], version_description: `Creates the ${tool.name} with a consented emailed report`,
    conversation_config: { conversation: { max_duration_seconds: 1200 }, agent: { first_message: tool.firstMessage, language: "en", max_conversation_duration_message: "We have reached the 20 minute conversation limit. Your progress is still here and you can start another conversation whenever you are ready.", prompt: { prompt: `${tool.prompt}\n\nWhen the user has a useful working plan, offer to email it. First summarise it in the conversation. Before asking for contact details, explain what will be emailed and that providing an email address after this explanation means they want the report sent there. Ask for their name and email; use their organisation only if already known. Do not repeat the email, ask for a second confirmation or continue asking questions once the email is supplied. Immediately call send_bridgit_toolkit_report once with toolkit_type=${tool.key} and consent=true. Put the tailored plan in recommendation, numbered actions in actions, key considerations in considerations and measures/resources/options in options. Never include confidential case information. Confirm successful delivery briefly and end the conversation. On failure, offer contact@bridgit.care and wrap up.`, tool_ids: [reportToolId], knowledge_base: [{ type: "text", name: tool.knowledgeName, id: knowledgeId, usage_mode: "auto" }] } } },
    platform_settings: { widget: { variant: "full", placement: "bottom-right", bg_color: "#ffffff", text_color: "#171b2f", btn_color: "#6d2bc3", btn_text_color: "#ffffff", border_color: "#dfd3ee", focus_color: "#c02fb1", show_avatar_when_collapsed: true, disable_banner: true, language_selector: false, supports_text_only: true, text_input_enabled: true, transcript_enabled: true, text_contents: { main_label: tool.name.replace("Bridgit - ", ""), start_call: "Start the guided tool", input_placeholder: "Tell me where you would like to start..." } } }
  } });
  return agent.agent_id;
}

const secretId = await ensureSecret();
console.log("Secure report connection ready.");
const reportToolId = await ensureReportTool(secretId);
console.log("Report tool ready.");
const result = {};
for (const tool of tools) {
  console.log(`Provisioning ${tool.name}...`);
  const knowledgeId = await ensureKnowledge(tool);
  result[tool.key] = await ensureAgent(tool, knowledgeId, reportToolId);
}
console.log(JSON.stringify({ report_tool_id: reportToolId, agents: result }, null, 2));
