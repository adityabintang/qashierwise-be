# Requirements Document

## Introduction

Fitur Conversation Summarization untuk AI Agent yang meringkas percakapan panjang menjadi konteks singkat. Tujuannya adalah menghemat token saat memanggil LLM dengan mengganti history percakapan panjang dengan ringkasan terstruktur dalam format JSON. Fitur ini berlaku untuk WhatsApp chat maupun Test AI Agent popup di dashboard.

## Glossary

- **Conversation_Summarizer**: Modul yang meringkas percakapan user dan bot menjadi informasi inti
- **AI_Agent_Service**: Service yang memproses pesan WhatsApp dan menghasilkan respons AI
- **Conversation**: Sesi percakapan antara user dan AI Agent yang disimpan di database dengan TTL 1 jam
- **Summary_Context**: Ringkasan percakapan dalam format JSON yang digunakan sebagai konteks untuk LLM
- **Intent**: Tujuan utama user dalam percakapan (browse_menu, order_food, reservation, payment, general_question, unknown)
- **Sliding_Window**: Mekanisme yang menyimpan hanya N pesan terakhir dalam conversation
- **Test_AI_Agent_Popup**: Fitur popup di dashboard untuk menguji AI Agent tanpa WhatsApp

## Requirements

### Requirement 1: Conversation Memory TTL

**User Story:** As a system, I want conversation memory to expire after 1 hour, so that stale context is automatically cleared and resources are freed.

#### Acceptance Criteria

1. WHEN a new conversation is created, THE Conversation SHALL set expires_at to 1 hour from creation time
2. WHEN a message is added to conversation, THE Conversation SHALL extend expires_at to 1 hour from current time
3. WHEN expires_at is reached, THE Conversation SHALL be considered expired and eligible for cleanup
4. WHEN processing a message for an expired conversation, THE AI_Agent_Service SHALL create a new conversation

### Requirement 2: Conversation Summarization Trigger

**User Story:** As a system, I want to automatically trigger summarization when conversation reaches certain thresholds, so that token usage is optimized without manual intervention.

#### Acceptance Criteria

1. WHEN the conversation message count reaches 6 or more messages, THE Conversation_Summarizer SHALL be triggered
2. WHEN the total input token estimate reaches 800 or more tokens, THE Conversation_Summarizer SHALL be triggered
3. WHEN the user intent changes (e.g., from menu browsing to reservation), THE Conversation_Summarizer SHALL be triggered
4. WHEN the user sends only short confirmations like "ok", "ya", or single words, THE Conversation_Summarizer SHALL NOT be triggered
5. WHEN the conversation has fewer than 3 substantive messages, THE Conversation_Summarizer SHALL NOT be triggered

### Requirement 3: Summary Generation

**User Story:** As a system, I want to generate structured summaries from conversations, so that the AI can maintain context efficiently.

#### Acceptance Criteria

1. WHEN summarization is triggered, THE Conversation_Summarizer SHALL call the LLM with a dedicated summarization system prompt
2. THE Conversation_Summarizer SHALL output a JSON object with fields: summary, intent, key_data, and missing_information
3. WHEN generating summary, THE Conversation_Summarizer SHALL only use information explicitly mentioned in the conversation
4. THE Conversation_Summarizer SHALL NOT add assumptions or interpretations to the summary
5. WHEN the conversation contains product mentions, THE Conversation_Summarizer SHALL extract product names into key_data.products array
6. WHEN the conversation contains reservation details, THE Conversation_Summarizer SHALL extract date, time, and people_count into key_data
7. WHEN the conversation contains order items, THE Conversation_Summarizer SHALL extract items into key_data.order_items array
8. WHEN required information is missing, THE Conversation_Summarizer SHALL list missing fields in missing_information array

### Requirement 4: Summary Storage and Retrieval

**User Story:** As a system, I want to store and retrieve conversation summaries, so that they can be used in subsequent LLM calls.

#### Acceptance Criteria

1. WHEN a summary is generated, THE Conversation_Summarizer SHALL store it in the conversation's order_context field
2. WHEN a new message arrives after summarization, THE AI_Agent_Service SHALL include the summary as context instead of full message history
3. THE Conversation_Summarizer SHALL preserve the last 2-3 messages after summarization for immediate context
4. WHEN the conversation is reset or expires, THE Conversation_Summarizer SHALL clear the stored summary

### Requirement 5: Intent Classification

**User Story:** As a system, I want to classify user intent from conversations, so that the AI can respond appropriately and trigger summarization on intent changes.

#### Acceptance Criteria

1. THE Conversation_Summarizer SHALL classify intent into one of: browse_menu, order_food, reservation, payment, general_question, or unknown
2. WHEN the classified intent differs from the previous intent, THE Conversation_Summarizer SHALL flag an intent change
3. THE Conversation_Summarizer SHALL store the current intent in the conversation context for comparison

### Requirement 6: Token Estimation

**User Story:** As a system, I want to estimate token count of conversations, so that summarization can be triggered based on token thresholds.

#### Acceptance Criteria

1. THE Conversation_Summarizer SHALL estimate token count using character-based approximation (1 token ≈ 4 characters for English, 1 token ≈ 2 characters for Indonesian)
2. WHEN estimating tokens, THE Conversation_Summarizer SHALL include both user and AI messages
3. THE Conversation_Summarizer SHALL provide a method to check if token threshold is exceeded

### Requirement 7: Summary Format Validation

**User Story:** As a system, I want to validate summary format, so that downstream systems can reliably parse the summary.

#### Acceptance Criteria

1. THE Conversation_Summarizer SHALL validate that the LLM output is valid JSON
2. IF the LLM output is not valid JSON, THEN THE Conversation_Summarizer SHALL retry once with a stricter prompt
3. IF validation fails after retry, THEN THE Conversation_Summarizer SHALL fall back to using the original message history
4. THE Conversation_Summarizer SHALL validate that required fields (summary, intent, key_data, missing_information) are present

### Requirement 8: Integration with AI Agent Service

**User Story:** As a developer, I want the summarization to integrate seamlessly with the existing AI Agent Service, so that the feature works without breaking existing functionality.

#### Acceptance Criteria

1. WHEN processing a message, THE AI_Agent_Service SHALL check if summarization is needed before calling the main LLM
2. WHEN summary context exists, THE AI_Agent_Service SHALL format it as a context prefix in the system prompt
3. THE AI_Agent_Service SHALL maintain backward compatibility with conversations that don't have summaries
4. WHEN an error occurs during summarization, THE AI_Agent_Service SHALL fall back to using the original message history

### Requirement 9: Test AI Agent Popup Integration

**User Story:** As a user, I want to test AI Agent with summarization in the dashboard popup, so that I can verify the feature works before deploying to WhatsApp.

#### Acceptance Criteria

1. WHEN testing AI Agent via popup, THE Test_AI_Agent_Popup SHALL use the same summarization logic as WhatsApp
2. THE Test_AI_Agent_Popup SHALL display the current conversation summary when available
3. THE Test_AI_Agent_Popup SHALL provide a "Reset Conversation" button to clear summary and start fresh
4. WHEN conversation in popup reaches summarization threshold, THE Test_AI_Agent_Popup SHALL trigger summarization
5. THE Test_AI_Agent_Popup SHALL show visual indicator when summarization is active (e.g., "Context summarized")
6. THE Test_AI_Agent_Popup conversation SHALL also expire after 1 hour of inactivity

### Requirement 10: Summary Serialization Round-Trip

**User Story:** As a developer, I want summaries to be correctly serialized and deserialized, so that data integrity is maintained.

#### Acceptance Criteria

1. FOR ALL valid Summary_Context objects, serializing to JSON then deserializing SHALL produce an equivalent object
2. THE Conversation_Summarizer SHALL handle special characters and Unicode in summaries correctly
3. THE Conversation_Summarizer SHALL preserve all numeric values (prices, quantities, counts) accurately during serialization
