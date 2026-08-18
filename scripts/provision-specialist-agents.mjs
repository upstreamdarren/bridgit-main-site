import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";

const API_ROOT = "https://api.elevenlabs.io/v1";
const BASE_AGENT_ID = "agent_9001k5y0ara8ej0tqx19x61wcqkq";
const LEAD_ENDPOINT = "https://bridgit.care/wp-json/bridgit/v1/lead";
const SECRET_NAME = "bridgit_wordpress_lead_webhook";
const TOOL_NAME = "send_bridgit_sales_lead";

const apiKey = process.env.ELEVENLABS_API_KEY?.trim();
const leadSecret = process.env.BRIDGIT_LEAD_WEBHOOK_SECRET?.trim();

if (!apiKey || !leadSecret) {
  throw new Error(
    "ELEVENLABS_API_KEY and BRIDGIT_LEAD_WEBHOOK_SECRET must both be available."
  );
}

const definitions = [
  {
    slug: "homepage",
    agentId: BASE_AGENT_ID,
    name: "Bridgit - Main Site",
    promptFile: "../docs/elevenlabs/prompts/homepage.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/homepage.md",
    knowledgeName: "Bridgit Sales Knowledge - Main Website v2",
    firstMessage: "Hi, I'm Bridgit. I can explain how our digital coaches help councils, health services, charities, social enterprises and employers extend trusted human support. You could ask what Bridgit does, how a first project works, or which solution fits your organisation. What would you like to explore?",
    widgetLabel: "Ask Bridgit"
  },
  {
    slug: "adult-carers",
    name: "Bridgit - Adult Carer Services",
    promptFile: "../docs/elevenlabs/prompts/adult-carers.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/adult-carers.md",
    knowledgeName: "Bridgit Sales Knowledge - Adult Carer Services v1",
    firstMessage:
      "Hi, I'm Bridgit. I can show you how digital coaches help carer services reach hidden and unpaid carers earlier while keeping human expertise at the centre. You could ask: How can we reach hidden carers earlier? How would Bridgit work alongside our team? Or what would a practical first deployment look like? What would be most useful to explore?",
    widgetLabel: "Ask about carer services"
  },
  {
    slug: "local-support",
    name: "Bridgit - Local Support",
    promptFile: "../docs/elevenlabs/prompts/local-support.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/local-support.md",
    knowledgeName: "Bridgit Sales Knowledge - Local Support v1",
    firstMessage:
      "Hi, I'm Bridgit. I can explain how councils and neighbourhood partners use digital coaches to offer earlier, joined-up support while protecting specialist team capacity. You could ask: How can we reduce pressure on our front door? How do council and VCSE pathways connect? Or what would you need from us to get started? What would be most useful to explore?",
    widgetLabel: "Ask about Council Front Door"
  },
  {
    slug: "social-enterprises",
    name: "Bridgit - Social Enterprises",
    promptFile: "../docs/elevenlabs/prompts/social-enterprises.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/social-enterprises.md",
    knowledgeName: "Bridgit Sales Knowledge - Social Enterprises v1",
    firstMessage:
      "Hi, I'm Bridgit. I can help you explore how a digital coach could extend your social enterprise's service model without losing its purpose or human relationships. You could ask: How could a coach help us reach more people? How could we evidence social impact? Or what is the simplest first use case to launch? What would be most useful to explore?",
    widgetLabel: "Ask about scaling impact"
  },
  {
    slug: "young-carers",
    name: "Bridgit - Young Carers",
    promptFile: "../docs/elevenlabs/prompts/young-carers.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/young-carers.md",
    knowledgeName: "Bridgit Sales Knowledge - Young Carers v1",
    firstMessage: "Hi, I'm Bridgit. I can explain how age-appropriate digital coaching helps young-carer services reach young people earlier while keeping safeguarding and trusted adults central. What would you like to explore?",
    widgetLabel: "Ask about young carers"
  },
  {
    slug: "employers",
    name: "Bridgit - Employers",
    promptFile: "../docs/elevenlabs/prompts/employers.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/employers.md",
    knowledgeName: "Bridgit Sales Knowledge - Employers v1",
    firstMessage: "Hi, I'm Bridgit. I can show how private, early digital support can help employees manage caring and life pressures while complementing managers and people teams. What would be useful to explore?",
    widgetLabel: "Ask about workplace support"
  },
  {
    slug: "care-leavers",
    name: "Bridgit - Care Leavers",
    promptFile: "../docs/elevenlabs/prompts/care-leavers.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/care-leavers.md",
    knowledgeName: "Bridgit Sales Knowledge - Care Leavers v1",
    firstMessage: "Hi, I'm Bridgit. I can explain how digital coaching gives care leavers consistent guidance between appointments while strengthening the relationship with personal advisers. What would you like to explore?",
    widgetLabel: "Ask about care-leaver support"
  },
  {
    slug: "healthy-ageing",
    name: "Bridgit - Healthy Ageing",
    promptFile: "../docs/elevenlabs/prompts/healthy-ageing.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/healthy-ageing.md",
    knowledgeName: "Bridgit Sales Knowledge - Healthy Ageing v1",
    firstMessage: "Hi, I'm Bridgit. I can explain how accessible digital coaching connects older adults to earlier practical and community support through voice, web and messaging. What would you like to explore?",
    widgetLabel: "Ask about healthy ageing"
  },
  {
    slug: "nhs",
    name: "Bridgit - NHS Partners",
    promptFile: "../docs/elevenlabs/prompts/nhs.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/nhs.md",
    knowledgeName: "Bridgit Sales Knowledge - NHS Partners v1",
    firstMessage: "Hi, I'm Bridgit. I can explain how digital coaches extend safe, preventative and non-clinical support around NHS appointments and community pathways. What would be most useful to explore?",
    widgetLabel: "Ask about NHS partnerships"
  },
  {
    slug: "corporate-partners",
    name: "Bridgit - Corporate Partners",
    promptFile: "../docs/elevenlabs/prompts/corporate-partners.md",
    knowledgeFile: "../docs/elevenlabs/knowledge/corporate-partners.md",
    knowledgeName: "Bridgit Sales Knowledge - Corporate Partners v1",
    firstMessage: "Hi, I'm Bridgit. I can explain two partnership routes: extending support for your own people, or helping social enterprises deliver more impact using Bridgit technology. Which route would you like to explore?",
    widgetLabel: "Ask about partnership"
  }
];

async function request(path, { method = "GET", body } = {}, attempt = 1) {
  const response = await fetch(`${API_ROOT}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "xi-api-key": apiKey
    },
    body: body === undefined ? undefined : JSON.stringify(body)
  });

  const text = await response.text();
  let result;
  try {
    result = text ? JSON.parse(text) : null;
  } catch {
    result = text;
  }

  if (!response.ok) {
    if (response.status >= 500 && attempt < 4) {
      await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
      return request(path, { method, body }, attempt + 1);
    }
    const detail = result?.detail?.message || result?.detail || result?.message || result;
    throw new Error(`${method} ${path} failed (${response.status}): ${JSON.stringify(detail)}`);
  }

  return result;
}

async function ensureSecret() {
  const result = await request("/convai/secrets?page_size=100");
  const existing = (result.secrets ?? []).find((secret) => secret.name === SECRET_NAME);
  if (existing) return existing.secret_id;

  const created = await request("/convai/secrets", {
    method: "POST",
    body: { type: "new", name: SECRET_NAME, value: leadSecret }
  });
  return created.secret_id;
}

function literal(type, description, extra = {}) {
  return { type, description, ...extra };
}

async function ensureLeadTool(secretId) {
  const result = await request("/convai/tools?page_size=100");
  const existing = (result.tools ?? []).find(
    (tool) => tool.tool_config?.name === TOOL_NAME
  );
  const toolConfig = {
        type: "webhook",
        name: TOOL_NAME,
        description:
          "Send a consented business enquiry to the Bridgit team. Call only after the visitor has supplied the required contact details, heard what will be sent, confirmed the details and explicitly agreed to the follow-up. Never include health, safeguarding, beneficiary or service-user case information.",
        response_timeout_secs: 20,
        interruption_mode: "disable_during_tool",
        pre_tool_speech: "off",
        tool_error_handling_mode: "summarized",
        execution_mode: "immediate",
        api_schema: {
          url: LEAD_ENDPOINT,
          method: "POST",
          content_type: "application/json",
          request_headers: {
            "X-Bridgit-Lead-Secret": { secret_id: secretId }
          },
          request_body_schema: {
            type: "object",
            property_kind: "object",
            description: "Consented business contact details for Bridgit follow-up.",
            required: [
              "name",
              "email",
              "organisation",
              "interest",
              "summary",
              "source_page",
              "consent"
            ],
            properties: {
              name: literal("string", "Visitor's full name."),
              email: literal("string", "Visitor's confirmed work email address."),
              organisation: literal("string", "Visitor's organisation."),
              role: literal(["string", "null"], "Visitor's role, or null when not provided."),
              phone: literal(["string", "null"], "Visitor's phone number, or null when not provided."),
              interest: literal("string", "The service challenge or Bridgit offer the visitor wants to discuss."),
              summary: literal(
                "string",
                "A short business-only summary. Exclude health, safeguarding, beneficiary and service-user personal information."
              ),
              source_page: literal("string", "Landing page where the enquiry started.", {
                enum: ["homepage", "adult-carers", "local-support", "social-enterprises", "young-carers", "employers", "care-leavers", "healthy-ageing", "nhs", "corporate-partners"]
              }),
              consent: literal(
                "boolean",
                "True only when the visitor explicitly agrees that these details can be sent to Bridgit for follow-up."
              )
            }
          },
          response_body_schema: {
            type: "object",
            property_kind: "object",
            required: ["success", "message"],
            properties: {
              success: literal("boolean", "Whether the enquiry was accepted."),
              message: literal("string", "A short result message.")
            }
          }
        }
      };

  if (existing) {
    await request(`/convai/tools/${existing.id}`, { method: "PATCH", body: { tool_config: toolConfig } });
    return existing.id;
  }

  const created = await request("/convai/tools", {
    method: "POST",
    body: { tool_config: toolConfig }
  });

  return created.id;
}

async function ensureKnowledge(definition) {
  const result = await request(
    "/convai/knowledge-base?page_size=100&folders_first=true&sort_by=name&sort_direction=asc"
  );
  const existing = (result.documents ?? []).find(
    (document) => document.name === definition.knowledgeName
  );
  const text = await readFile(new URL(definition.knowledgeFile, import.meta.url), "utf8");
  if (existing) {
    await request(`/convai/knowledge-base/${existing.id}`, {
      method: "PATCH",
      body: { name: definition.knowledgeName, text }
    });
    return existing.id;
  }

  const created = await request("/convai/knowledge-base/text", {
    method: "POST",
    body: { name: definition.knowledgeName, text }
  });
  return created.id;
}

async function listAgents() {
  const result = await request(
    "/convai/agents?page_size=100&sort_by=name&sort_direction=asc"
  );
  return result.agents ?? [];
}

async function ensureAgent(definition, knowledgeId, leadToolId, agents) {
  let agent = definition.agentId
    ? { agent_id: definition.agentId, name: definition.name }
    : agents.find((candidate) => candidate.name === definition.name);
  if (!agent) {
    const created = await request(`/convai/agents/${BASE_AGENT_ID}/duplicate`, {
      method: "POST",
      body: { name: definition.name }
    });
    agent = { agent_id: created.agent_id, name: definition.name };
  }

  const prompt = await readFile(new URL(definition.promptFile, import.meta.url), "utf8");
  await request(`/convai/agents/${agent.agent_id}`, {
    method: "PATCH",
    body: {
      name: definition.name,
      tags: ["bridgit-website", "sales", definition.slug],
      version_description: "Specialist landing-page coach with consented lead handover",
      conversation_config: {
        agent: {
          first_message: definition.firstMessage,
          language: "en",
          prompt: {
            prompt,
            tool_ids: [leadToolId],
            knowledge_base: [
              {
                type: "text",
                name: definition.knowledgeName,
                id: knowledgeId,
                usage_mode: "auto"
              }
            ]
          }
        }
      },
      platform_settings: {
        widget: {
          variant: "full",
          placement: "bottom-right",
          bg_color: "#ffffff",
          text_color: "#171b2f",
          btn_color: "#6d2bc3",
          btn_text_color: "#ffffff",
          border_color: "#dfd3ee",
          focus_color: "#c02fb1",
          show_avatar_when_collapsed: true,
          disable_banner: true,
          language_selector: true,
          supports_text_only: true,
          text_input_enabled: true,
          transcript_enabled: true,
          text_contents: {
            main_label: definition.widgetLabel,
            start_call: "Talk with Bridgit",
            input_placeholder: "Ask Bridgit a question..."
          }
        }
      }
    }
  });

  return { slug: definition.slug, name: definition.name, agent_id: agent.agent_id };
}

const secretId = await ensureSecret();
const leadToolId = await ensureLeadTool(secretId);
const agents = await listAgents();
const provisioned = [];

for (const definition of definitions) {
  const knowledgeId = await ensureKnowledge(definition);
  provisioned.push(
    await ensureAgent(definition, knowledgeId, leadToolId, agents)
  );
}

console.log(JSON.stringify({ lead_tool_id: leadToolId, agents: provisioned }, null, 2));
