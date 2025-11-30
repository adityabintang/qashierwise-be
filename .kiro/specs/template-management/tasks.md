# Implementation Plan

- [x] 1. Create TemplateService with validation methods





  - [x] 1.1 Create TemplateService class with basic structure


    - Create `app/Services/TemplateService.php`
    - Implement constructor with HTTP client and config injection
    - _Requirements: 1.2, 2.2, 3.2_

  - [x] 1.2 Implement template name validation method
    - Create `validateTemplateName()` method
    - Validate lowercase alphanumeric and underscores only
    - Return validation result with error message if invalid
    - _Requirements: 1.3, 5.2_
  - [x] 1.3 Write property test for template name validation






    - **Property 1: Template Name Validation**
    - **Validates: Requirements 1.3, 5.2**

  - [x] 1.4 Implement footer length validation method
    - Create `validateFooter()` method
    - Validate max 60 characters
    - _Requirements: 1.7_
  - [x] 1.5 Write property test for footer length validation






    - **Property 2: Footer Length Validation**
    - **Validates: Requirements 1.7**

  - [x] 1.6 Implement body length validation method
    - Create `validateBody()` method
    - Validate max 1024 characters
    - _Requirements: 5.3_
  - [x] 1.7 Write property test for body length validation






    - **Property 4: Body Length Validation**
    - **Validates: Requirements 5.3**

  - [x] 1.8 Implement button limit validation method
    - Create `validateButtons()` method
    - Validate max 10 quick reply OR max 2 CTA buttons
  - [x] 1.9 Write property test for button limit validation



  - [ ] 1.9 Write property test for button limit validation


    - **Property 3: Button Limit Validation**
    - **Validates: Requirements 1.8**

  - [x] 1.10 Implement required field validation method
    - Create `validateRequiredFields()` method
    - Check name, category, language, body are present
    - _Requirements: 5.4_
  - [x] 1.11 Write property test for required field validation






    - **Property 5: Required Field Validation**
    - **Validates: Requirements 5.4**
  - [x] 1.12 Implement variable sequence validation method

    - Create `validateVariableSequence()` method
    - Check variables are sequential ({{1}}, {{2}}, etc.)
    - _Requirements: 5.5_
  - [ ]* 1.13 Write property test for variable sequence validation
    - **Property 6: Variable Sequence Validation**
    - **Validates: Requirements 5.5**
- [x] 2. Implement TemplateService API methods




- [ ] 2. Implement TemplateService API methods

  - [x] 2.1 Implement buildComponents method


    - Create `buildComponents()` method
    - Transform form data to WhatsApp API component format
    - Handle HEADER, BODY, FOOTER, BUTTONS components
    - _Requirements: 1.5, 1.6, 1.7, 1.8_

  - [x] 2.2 Implement createTemplate method

    - Create `createTemplate()` method
    - Build API request payload
    - Send POST request to WhatsApp Business Management API
    - Handle response and errors
    - _Requirements: 1.2, 1.9, 1.10_
  - [x] 2.3 Implement updateTemplate method


    - Create `updateTemplate()` method
    - Build API request payload for update
    - Send POST request to WhatsApp API (template edit endpoint)
    - Handle response and errors
    - _Requirements: 2.2, 2.4, 2.5_
  - [x] 2.4 Implement deleteTemplate method


    - Create `deleteTemplate()` method
    - Send DELETE request using template name
    - Handle response and errors
    - _Requirements: 3.2, 3.3, 3.4, 3.5_
  - [x] 2.5 Implement template data serialization


    - Create `serializeTemplate()` and `deserializeTemplate()` methods
    - Handle JSON encoding/decoding of components
  - [x] 2.6 Write property test for template data round-trip




  - [ ] 2.6 Write property test for template data round-trip


    - **Property 8: Template Data Round-Trip**
    - **Validates: Requirements 6.1, 6.2, 6.3**

- [x] 3. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Add controller endpoints for template CRUD





  - [x] 4.1 Add createTemplate endpoint to WhatsAppController


    - Add `createTemplate()` method
    - Validate request data using TemplateService
    - Call TemplateService to create template
    - Store result in database
    - Return JSON response
    - _Requirements: 1.2, 1.9, 1.10_
  - [x] 4.2 Add updateTemplate endpoint to WhatsAppController


    - Add `updateTemplate()` method
    - Validate request data
    - Call TemplateService to update template
    - Update database record
    - Return JSON response
    - _Requirements: 2.2, 2.4, 2.5_

  - [x] 4.3 Add deleteTemplate endpoint to WhatsAppController

    - Add `deleteTemplate()` method
    - Call TemplateService to delete template
    - Remove from database on success
    - Return JSON response
    - _Requirements: 3.2, 3.3, 3.4_

  - [x] 4.4 Register new routes in api.php

    - Add POST route for template creation
    - Add PUT route for template update
    - Add DELETE route for template deletion
    - _Requirements: 1.2, 2.2, 3.2_

- [x] 5. Implement frontend template creation modal



  - [x] 5.1 Add create template modal HTML structure

    - Add modal container with form fields
    - Include name, category, language dropdowns
    - Add component sections (header, body, footer, buttons)
    - _Requirements: 1.1, 1.4, 1.5, 1.6, 1.7, 1.8_
  - [x] 5.2 Implement template form Alpine.js component

    - Create `templateForm()` Alpine component
    - Implement form state management
    - Add validation methods
    - Implement API submission
    - _Requirements: 1.2, 5.1_

  - [x] 5.3 Implement real-time template preview


    - Create preview panel in modal
    - Update preview on form changes
    - Display variable placeholders
    - Show media header icons
  - [x] 5.4 Write property test for preview variable rendering




  - [ ] 5.4 Write property test for preview variable rendering


    - **Property 7: Preview Variable Rendering**
    - **Validates: Requirements 4.3**

- [x] 6. Implement frontend template edit functionality





  - [x] 6.1 Add edit template modal


    - Reuse create modal structure
    - Pre-populate form with existing template data
    - Disable name field (read-only)
    - _Requirements: 2.1, 2.3_

  - [x] 6.2 Implement edit form submission

    - Add update API call
    - Handle success/error responses
    - Refresh template list on success
    - _Requirements: 2.2, 2.4, 2.5_

- [x] 7. Implement frontend template delete functionality



  - [x] 7.1 Add delete confirmation modal


    - Create confirmation dialog
    - Display template name in dialog
    - Add cancel and confirm buttons
    - _Requirements: 3.1_

  - [x] 7.2 Implement delete confirmation handler

    - Add delete API call
    - Handle success/error responses
    - Remove template from list on success
    - _Requirements: 3.2, 3.3, 3.4_

- [x] 8. Add action buttons to template cards





  - [x] 8.1 Update template card UI


    - Add Edit button to each template card
    - Add Delete button to each template card
    - Style buttons consistently with existing UI
    - _Requirements: 2.1, 3.1_

- [x] 9. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Final integration and polish





  - [x] 10.1 Add loading states and error handling


    - Show loading spinner during API calls
    - Display error messages from API
    - Preserve form state on errors
    - _Requirements: 1.9, 2.4, 3.4_
  - [x] 10.2 Add success notifications


    - Show toast notification on successful create
    - Show toast notification on successful update
    - Show toast notification on successful delete
    - _Requirements: 1.10, 2.5, 3.3_

- [x] 11. Final Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.
