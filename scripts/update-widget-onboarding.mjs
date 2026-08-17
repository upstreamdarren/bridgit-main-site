const API_ROOT = "https://api.elevenlabs.io/v1";
const apiKey = process.env.ELEVENLABS_API_KEY?.trim();

if (!apiKey) {
  throw new Error("ELEVENLABS_API_KEY is not available in this terminal session.");
}

const agents = [
  {
    id: "agent_9001k5y0ara8ej0tqx19x61wcqkq",
    firstMessage:
      "Hi, I'm Bridgit. Ask me anything about our coaches and how we work. You could ask: What does Bridgit do? How could Bridgit support our organisation? Or what would a first project look like?",
    label: "Ask Bridgit",
    placeholder: "Try: How could Bridgit support our organisation?"
  },
  {
    id: "agent_3701m07t4y30f1dsmqsm7t67rcde",
    firstMessage:
      "Hi, I'm Bridgit. I can show you how digital coaches help carer services reach hidden and unpaid carers earlier while keeping human expertise at the centre. You could ask: How can we reach hidden carers earlier? How would Bridgit work alongside our team? Or what would a practical first deployment look like?",
    label: "Ask about carer services",
    placeholder: "Try: How could we reach hidden carers earlier?"
  },
  {
    id: "agent_7601m07t55rvfnmr3dk6jsrs6rke",
    firstMessage:
      "Hi, I'm Bridgit. I can explain how councils and neighbourhood partners use digital coaches to offer earlier, joined-up support while protecting specialist team capacity. You could ask: How can we reduce pressure on our front door? How do council and VCSE pathways connect? Or what would you need from us to get started?",
    label: "Ask about Council Front Door",
    placeholder: "Try: How could this reduce front-door pressure?"
  },
  {
    id: "agent_0301m07t5czxep1tzytceg4wtw1y",
    firstMessage:
      "Hi, I'm Bridgit. I can help you explore how a digital coach could extend your social enterprise's service model without losing its purpose or human relationships. You could ask: How could a coach help us reach more people? How could we evidence social impact? Or what is the simplest first use case to launch?",
    label: "Ask about scaling impact",
    placeholder: "Try: How could a coach help us scale our impact?"
  }
];

async function updateAgent(agent) {
  const response = await fetch(`${API_ROOT}/convai/agents/${agent.id}`, {
    method: "PATCH",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "xi-api-key": apiKey
    },
    body: JSON.stringify({
      version_description: "Add useful conversation starters and remove the standard widget banner",
      conversation_config: {
        agent: { first_message: agent.firstMessage }
      },
      platform_settings: {
        widget: {
          disable_banner: true,
          text_contents: {
            main_label: agent.label,
            start_call: "Talk with Bridgit",
            start_chat: "Chat with Bridgit",
            input_placeholder: agent.placeholder
          }
        }
      }
    })
  });

  if (!response.ok) {
    const detail = await response.text();
    throw new Error(`Could not update ${agent.id} (${response.status}): ${detail}`);
  }

  return agent.id;
}

for (const agent of agents) {
  const id = await updateAgent(agent);
  console.log(`Updated ${id}`);
}
