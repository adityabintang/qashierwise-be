# Implementation Plan: Conversation Summarization

## Overview

Implementasi fitur Conversation Summarization akan dilakukan secara incremental, dimulai dari utility classes (TokenEstimator, SummaryValidator), kemudian core logic (ConversationSummarizer, IntentTracker), integrasi dengan model, dan akhirnya integrasi dengan AiAgentService. Setiap step akan divalidasi dengan tests untuk memastikan correctness.

## Tasks

- [x] 1. Setup dan create utility classes
  - [x] 1.1 Create TokenEstimator class
    - Implement `estimateTokens()` method dengan approximation 1 token ≈ 2 chars untuk Indonesian
    - Implement `estimateConversationTokens()` untuk menghitung total tokens dari array messages
    - Implement `exceedsThreshold()` untuk check apakah conversation melebihi threshold
    - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 1.2 Write property test for TokenEstimator
    - **Property 9: Token Estimation Consistency**
    - **Validates: Requirements 5.1**

  - [x] 1.3 Create SummaryValidator class
    - Implement `validate()` method untuk validasi struktur summary
    - Implement `hasRequiredFields()` untuk check required fields (summary, intent, key_data, missing_information)
    - Implement `getErrors()` untuk return validation errors
    - _Requirements: 6.1, 6.4_

  - [ ]* 1.4 Write property test for SummaryValidator
    - **Property 4: Summary JSON Validity**
    - **Property 5: Summary Required Fields**
    - **Validates: Requirements 2.2, 6.1, 6.4**

- [x] 2. Create IntentTracker class
  - [x] 2.1 Implement IntentTracker methods
    - Implement `getCurrentIntent()` untuk get intent dari conversation context
    - Implement `hasIntentChanged()` untuk detect perubahan intent
    - Implement `updateIntent()` untuk update intent di conversation context
    - _Requirements: 4.1, 4.2, 4.3_

  - [ ]* 2.2 Write unit tests for IntentTracker
    - Test getCurrentIntent dengan berbagai conversation states
    - Test hasIntentChanged dengan intent changes
    - Test updateIntent dan verify storage
    - _Requirements: 4.1, 4.2, 4.3_

  - [ ]* 2.3 Write property test for intent classification
    - **Property 8: Intent Classification Validity**
    - **Validates: Requirements 4.1**

- [x] 3. Extend AiAgentConversation model
  - [x] 3.1 Add summary-related methods to AiAgentConversation
    - Implement `getSummary()` untuk retrieve summary dari order_context
    - Implement `setSummary()` untuk store summary di order_context
    - Implement `hasSummary()` untuk check apakah conversation punya summary
    - Implement `clearSummary()` untuk clear summary
    - Implement `getMessageCount()` untuk count messages
    - Implement `getRecentMessages()` untuk get last N messages
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ]* 3.2 Write unit tests for AiAgentConversation extensions
    - Test summary storage dan retrieval
    - Test message counting
    - Test recent messages retrieval
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [x] 4. Checkpoint - Ensure all utility tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Create ConversationSummarizer service
  - [x] 5.1 Implement shouldSummarize() method
    - Check message count threshold (≥6 messages)
    - Check token threshold (≥800 tokens) using TokenEstimator
    - Check for short confirmation messages (exclude "ok", "ya", etc)
    - Check minimum substantive messages (≥3)
    - _Requirements: 1.1, 1.2, 1.4, 1.5_

  - [ ]* 5.2 Write property tests for shouldSummarize
    - **Property 1: Summarization Trigger Consistency**
    - **Property 2: Token Threshold Detection**
    - **Property 3: Short Message Exclusion**
    - **Validates: Requirements 1.1, 1.2, 1.4**

  - [x] 5.3 Implement generateSummary() method
    - Build summarization system prompt
    - Call LLM API dengan messages dan summarization prompt
    - Parse JSON response dari LLM
    - Validate response menggunakan SummaryValidator
    - Implement retry logic jika JSON invalid (1 retry dengan stricter prompt)
    - Return null jika validation fails setelah retry
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 6.1, 6.2, 6.3_

  - [ ]* 5.4 Write property test for summary generation
    - **Property 7: Product Extraction Accuracy**
    - **Validates: Requirements 2.5**

  - [x] 5.5 Implement getContextForLLM() method
    - Check if conversation has summary
    - If has summary: return summary context + last 2-3 messages
    - If no summary: return full message history
    - Implement error handling dan fallback ke full messages
    - _Requirements: 3.2, 3.3, 7.4_

  - [ ]* 5.6 Write property test for context fallback
    - **Property 10: Context Fallback Safety**
    - **Validates: Requirements 7.4**

  - [x] 5.7 Implement storeSummary() and clearSummary() methods
    - Implement storeSummary untuk save summary ke conversation
    - Implement clearSummary untuk remove summary
    - Handle storage errors dengan logging
    - _Requirements: 3.1, 3.4_

  - [ ]* 5.8 Write property test for summary serialization
    - **Property 6: Summary Serialization Round-Trip**
    - **Validates: Requirements 8.1**

- [x] 6. Checkpoint - Ensure ConversationSummarizer tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Integrate ConversationSummarizer with AiAgentService
  - [x] 7.1 Inject ConversationSummarizer into AiAgentService
    - Add ConversationSummarizer to constructor dependencies
    - Update service provider bindings jika diperlukan
    - _Requirements: 7.1_

  - [x] 7.2 Modify processMessage() to use summarization
    - Setelah get/create conversation, check if summarization needed
    - If needed, call generateSummary dan storeSummary
    - Update getContextForLLM call untuk use summarizer
    - Ensure backward compatibility dengan conversations tanpa summary
    - _Requirements: 7.1, 7.2, 7.3_

  - [x] 7.3 Add intent change detection
    - After generating summary, extract intent
    - Use IntentTracker untuk check if intent changed
    - If intent changed, trigger summarization
    - _Requirements: 1.3, 4.2_

  - [x] 7.4 Implement error handling dan fallback
    - Wrap summarization calls dalam try-catch
    - On error, log dan fallback ke original message history
    - Ensure system continues working jika summarization fails
    - _Requirements: 7.4_

  - [ ]* 7.5 Write integration tests
    - Test full flow: message → summarization → LLM call dengan summary context
    - Test intent change detection across multiple messages
    - Test backward compatibility dengan conversations tanpa summary
    - Test error handling dan fallback scenarios
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 8. Add configuration dan monitoring
  - [x] 8.1 Add configuration options
    - Add config untuk message count threshold (default: 6)
    - Add config untuk token threshold (default: 800)
    - Add config untuk enable/disable summarization
    - Add config untuk minimum substantive messages (default: 3)

  - [x] 8.2 Add logging dan monitoring
    - Log when summarization is triggered
    - Log summary generation success/failure
    - Log token savings (original vs summarized)
    - Log intent changes

- [x] 9. Final checkpoint - End-to-end testing
  - Test dengan real conversations dari WhatsApp
  - Verify token savings dalam production-like scenarios
  - Verify summary quality dan context preservation
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- Integration tests ensure components work together correctly
- PHP akan digunakan sebagai bahasa implementasi (Laravel framework)
- PHPUnit akan digunakan untuk unit testing
- Untuk property-based testing, gunakan library seperti Eris atau implement custom generators
