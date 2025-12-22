# Requirements Document

## Introduction

Fitur AI Agent WhatsApp memungkinkan pengguna untuk mengkonfigurasi dan mengaktifkan AI chatbot yang terhubung dengan nomor WABA (WhatsApp Business Account) yang sudah terkoneksi melalui embedded signup. AI Agent ini dapat menjawab pertanyaan customer secara otomatis berdasarkan prompt yang dikonfigurasi oleh pengguna, serta memiliki kemampuan untuk mengakses data produk, membuat order, dan memproses pembayaran melalui integrasi dengan sistem POS yang sudah ada.

## Glossary

- **AI Agent**: Chatbot berbasis AI yang dapat merespons pesan WhatsApp secara otomatis berdasarkan konfigurasi prompt dan konteks yang diberikan
- **WABA**: WhatsApp Business Account - akun bisnis WhatsApp yang terhubung ke aplikasi
- **System Prompt**: Instruksi dasar yang mendefinisikan persona, fungsi, dan batasan AI Agent
- **Memory Window**: Jumlah pesan percakapan sebelumnya yang disimpan untuk memberikan konteks kepada AI Agent
- **Order Button**: Toggle switch yang mengaktifkan kemampuan AI Agent untuk mengakses fungsi ordering (produk, order, payment)
- **LLM**: Large Language Model - model AI yang digunakan untuk memproses dan menghasilkan respons
- **Function Calling**: Kemampuan AI untuk memanggil fungsi/API tertentu berdasarkan konteks percakapan

## Requirements

### Requirement 1

**User Story:** As a business owner, I want to configure my AI Agent's basic identity and behavior, so that the chatbot represents my business appropriately when interacting with customers.

#### Acceptance Criteria

1. WHEN a user accesses the AI Agent menu THEN the System SHALL display a configuration form with fields for agent name, persona description, business information, and operating hours
2. WHEN a user saves the system prompt configuration THEN the System SHALL persist the configuration to the database and associate it with the user's WhatsApp account
3. WHEN a user updates the system prompt THEN the System SHALL validate that required fields (agent name, persona) are not empty before saving
4. WHEN the system prompt is saved THEN the System SHALL serialize the configuration to JSON format for storage
5. WHEN the system prompt is retrieved THEN the System SHALL deserialize the JSON configuration back to structured data

### Requirement 2

**User Story:** As a business owner, I want to enable or disable the ordering capability for my AI Agent, so that I can control whether customers can place orders through WhatsApp chat.

#### Acceptance Criteria

1. WHEN a user toggles the order button switch to ON THEN the System SHALL enable the AI Agent to access product, order, and payment functions
2. WHEN a user toggles the order button switch to OFF THEN the System SHALL disable the AI Agent's access to ordering functions and respond only with informational answers
3. WHEN the order button is enabled THEN the System SHALL display a description explaining that customers will be able to inquire about products, check availability, view prices, and place orders through chat
4. WHEN the order button state changes THEN the System SHALL persist the new state immediately to the database

### Requirement 3

**User Story:** As a customer, I want to chat with the AI Agent to get information about the business, so that I can learn about products, operating hours, and other business details.

#### Acceptance Criteria

1. WHEN a customer sends a message to the connected WABA number THEN the System SHALL process the message through the AI Agent if AI Agent is enabled
2. WHEN the AI Agent processes a message THEN the System SHALL include the configured system prompt as context for the LLM
3. WHEN the AI Agent generates a response THEN the System SHALL send the response back to the customer via WhatsApp within 30 seconds
4. WHEN the AI Agent encounters an error during processing THEN the System SHALL send a fallback message apologizing for the inconvenience and suggesting to try again later
5. WHEN the AI Agent is disabled THEN the System SHALL not auto-respond and allow messages to be handled manually by the business owner

### Requirement 4

**User Story:** As a customer, I want the AI Agent to remember our conversation context, so that I don't have to repeat information during our chat session.

#### Acceptance Criteria

1. WHEN the AI Agent processes a new message THEN the System SHALL retrieve the previous messages from the memory window as conversation context
2. WHEN storing conversation history THEN the System SHALL maintain a configurable memory window size (default: 10 messages)
3. WHEN the memory window is full THEN the System SHALL remove the oldest messages to maintain the configured window size
4. WHEN retrieving conversation context THEN the System SHALL order messages chronologically from oldest to newest
5. WHEN a new conversation starts (no messages in 24 hours) THEN the System SHALL clear the memory window and start fresh

### Requirement 5

**User Story:** As a customer, I want to inquire about product availability and prices through chat, so that I can make informed purchasing decisions.

#### Acceptance Criteria

1. WHEN the order button is enabled AND a customer asks about products THEN the System SHALL query the product database and provide accurate information
2. WHEN the AI Agent retrieves product information THEN the System SHALL include product name, price, description, and stock availability
3. WHEN a product is out of stock THEN the System SHALL inform the customer that the product is currently unavailable
4. WHEN the AI Agent searches for products THEN the System SHALL use fuzzy matching to handle variations in product name queries

### Requirement 6

**User Story:** As a customer, I want to place an order through the WhatsApp chat, so that I can purchase products without leaving the messaging app.

#### Acceptance Criteria

1. WHEN the order button is enabled AND a customer confirms an order THEN the System SHALL create a new order in the POS system
2. WHEN creating an order THEN the System SHALL validate that all requested products are available in sufficient quantity
3. WHEN an order is created successfully THEN the System SHALL send an order confirmation message with order number and total amount
4. WHEN an order creation fails due to insufficient stock THEN the System SHALL inform the customer which products are unavailable
5. WHEN an order is created THEN the System SHALL associate the order with the customer's WhatsApp contact information

### Requirement 7

**User Story:** As a business owner, I want to view and manage AI Agent settings from a dedicated dashboard menu, so that I can easily configure and monitor my AI chatbot.

#### Acceptance Criteria

1. WHEN a user navigates to the AI Agent menu THEN the System SHALL display the current configuration status and settings
2. WHEN a user has no WhatsApp account connected THEN the System SHALL display a message prompting them to connect their WhatsApp account first
3. WHEN displaying the AI Agent menu THEN the System SHALL show the enabled/disabled status of the AI Agent
4. WHEN the AI Agent is enabled THEN the System SHALL display a visual indicator (green badge) showing active status

### Requirement 8

**User Story:** As a business owner, I want to enable or disable the AI Agent entirely, so that I can control when automated responses are active.

#### Acceptance Criteria

1. WHEN a user toggles the AI Agent master switch to ON THEN the System SHALL activate automated responses for incoming WhatsApp messages
2. WHEN a user toggles the AI Agent master switch to OFF THEN the System SHALL deactivate all automated responses
3. WHEN the AI Agent is disabled THEN the System SHALL continue to receive and store incoming messages without auto-responding
4. WHEN the AI Agent status changes THEN the System SHALL log the status change with timestamp for audit purposes

### Requirement 9

**User Story:** As a system administrator, I want the AI Agent to integrate with an LLM provider, so that the chatbot can generate intelligent responses.

#### Acceptance Criteria

1. WHEN the System processes a message through the AI Agent THEN the System SHALL send the prompt and context to the configured LLM provider API
2. WHEN the LLM API returns a response THEN the System SHALL parse and format the response for WhatsApp delivery
3. WHEN the LLM API call fails THEN the System SHALL retry up to 3 times with exponential backoff before sending a fallback message
4. WHEN the LLM API response exceeds WhatsApp message length limit (4096 characters) THEN the System SHALL truncate the response appropriately
5. WHEN function calling is required THEN the System SHALL define and register available functions (get_products, create_order, get_order_status) with the LLM

