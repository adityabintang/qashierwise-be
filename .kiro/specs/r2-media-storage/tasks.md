# Implementation Plan

- [x] 1. Configure R2 filesystem disk




  - [x] 1.1 Add R2 disk configuration to config/filesystems.php





    - Add 'r2' disk with S3 driver configuration
    - Configure endpoint, bucket, credentials from environment variables
    - Set visibility to public
  - [x] 1.2 Add R2 environment variables to .env.example

  - [x] 1.2 Add R2 environment variables to .env.example




    - Add R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, R2_URL, R2_REGION
    - _Requirements: 1.1_

- [x] 2. Create MediaStorageService




  - [x] 2.1 Create app/Services/MediaStorageService.php with core methods



    - Implement getDiskName() to return configured disk
    - Implement sanitizeFilename() to remove special characters
    - Implement generateUniqueFilename() with timestamp prefix
    - Implement generatePath() for media type paths
    - Implement store() to upload file to storage
    - Implement getPublicUrl() to return accessible URL
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  - [x] 2.2 Write property test for filename sanitization






    - **Property 2: Filename Sanitization**
    - **Validates: Requirements 6.2**
  - [ ]* 2.3 Write property test for unique filename generation
    - **Property 3: Unique Filename Generation**
    - **Validates: Requirements 6.3**
  - [ ]* 2.4 Write property test for path generation pattern
    - **Property 1: Path Generation Pattern**
    - **Validates: Requirements 2.1, 3.1, 4.1, 5.1**
  - [ ]* 2.5 Write property test for URL format validity
    - **Property 5: URL Format Validity**
    - **Validates: Requirements 7.3, 2.2, 3.2, 4.2, 5.2**

- [x] 3. Register MediaStorageService in service container





  - [x] 3.1 Register service in AppServiceProvider


    - Bind MediaStorageService as singleton
    - _Requirements: 6.1_

- [x] 4. Update WhatsAppController to use MediaStorageService





  - [x] 4.1 Inject MediaStorageService into WhatsAppController


    - Add service as constructor dependency
    - Remove storeMediaLocally() method
    - _Requirements: 6.1_
  - [x] 4.2 Update sendImageMessage() to use MediaStorageService


    - Replace storeMediaLocally() call with service->store()
    - Update content URL with returned URL
    - _Requirements: 2.1, 2.2, 2.4_
  - [x] 4.3 Update sendDocumentMessage() to use MediaStorageService


    - Replace storeMediaLocally() call with service->store()
    - Update content URL with returned URL
    - _Requirements: 3.1, 3.2, 3.4_
  - [x] 4.4 Update sendAudioMessage() to use MediaStorageService


    - Replace storeMediaLocally() call with service->store()
    - Update content URL with returned URL
    - _Requirements: 4.1, 4.2, 4.4_

  - [x] 4.5 Update sendVideoMessage() to use MediaStorageService

    - Replace storeMediaLocally() call with service->store()
    - Update content URL with returned URL
    - _Requirements: 5.1, 5.2, 5.4_

- [x] 5. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [ ]* 6. Add unit tests for MediaStorageService
  - [ ]* 6.1 Write unit tests for configuration selection
    - Test R2 disk selection when FILESYSTEM_DISK=r2
    - Test local disk selection when FILESYSTEM_DISK=public
    - _Requirements: 7.1, 7.2_
  - [ ]* 6.2 Write unit tests for error handling
    - Test upload failure returns error response
    - Test missing credentials fallback
    - _Requirements: 1.3, 2.3, 3.3, 4.3, 5.3_

- [x] 7. Final Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.
