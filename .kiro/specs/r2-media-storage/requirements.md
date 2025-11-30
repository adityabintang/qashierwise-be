# Requirements Document

## Introduction

Fitur ini memungkinkan penyimpanan file media WhatsApp (gambar, dokumen, audio, video) ke Cloudflare R2 Object Storage sebagai pengganti penyimpanan lokal. R2 menyediakan penyimpanan yang scalable, cost-effective, dan S3-compatible untuk menangani file media dengan volume tinggi.

## Glossary

- **R2**: Cloudflare R2 Object Storage, layanan penyimpanan objek yang S3-compatible
- **Media**: File yang dikirim melalui WhatsApp termasuk gambar, dokumen, audio, dan video
- **WhatsApp Media System**: Sistem yang menangani upload, penyimpanan, dan pengambilan file media WhatsApp
- **Public URL**: URL yang dapat diakses publik untuk mengambil file media yang tersimpan
- **Storage Disk**: Konfigurasi Laravel filesystem untuk menentukan lokasi penyimpanan

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want to configure R2 storage credentials, so that the application can connect to Cloudflare R2 bucket.

#### Acceptance Criteria

1. WHEN the application starts THEN the WhatsApp Media System SHALL read R2 configuration from environment variables (R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, R2_URL)
2. WHEN R2 credentials are configured THEN the WhatsApp Media System SHALL validate the connection by checking bucket accessibility
3. IF R2 credentials are missing or invalid THEN the WhatsApp Media System SHALL log an error message and fall back to local storage

### Requirement 2

**User Story:** As a user, I want to upload image files to R2 storage, so that images are stored in cloud storage instead of local disk.

#### Acceptance Criteria

1. WHEN a user uploads an image file THEN the WhatsApp Media System SHALL store the file in R2 bucket under the path "whatsapp/images/{timestamp}_{filename}"
2. WHEN an image is successfully uploaded to R2 THEN the WhatsApp Media System SHALL return a public URL for accessing the image
3. WHEN an image upload fails THEN the WhatsApp Media System SHALL return an error response with failure details
4. WHEN storing image metadata THEN the WhatsApp Media System SHALL save the R2 URL in the message content

### Requirement 3

**User Story:** As a user, I want to upload document files to R2 storage, so that documents are stored in cloud storage instead of local disk.

#### Acceptance Criteria

1. WHEN a user uploads a document file THEN the WhatsApp Media System SHALL store the file in R2 bucket under the path "whatsapp/documents/{timestamp}_{filename}"
2. WHEN a document is successfully uploaded to R2 THEN the WhatsApp Media System SHALL return a public URL for accessing the document
3. WHEN a document upload fails THEN the WhatsApp Media System SHALL return an error response with failure details
4. WHEN storing document metadata THEN the WhatsApp Media System SHALL save the R2 URL and original filename in the message content

### Requirement 4

**User Story:** As a user, I want to upload audio files to R2 storage, so that audio files are stored in cloud storage instead of local disk.

#### Acceptance Criteria

1. WHEN a user uploads an audio file THEN the WhatsApp Media System SHALL store the file in R2 bucket under the path "whatsapp/audio/{timestamp}_{filename}"
2. WHEN an audio file is successfully uploaded to R2 THEN the WhatsApp Media System SHALL return a public URL for accessing the audio
3. WHEN an audio upload fails THEN the WhatsApp Media System SHALL return an error response with failure details
4. WHEN storing audio metadata THEN the WhatsApp Media System SHALL save the R2 URL in the message content

### Requirement 5

**User Story:** As a user, I want to upload video files to R2 storage, so that video files are stored in cloud storage instead of local disk.

#### Acceptance Criteria

1. WHEN a user uploads a video file THEN the WhatsApp Media System SHALL store the file in R2 bucket under the path "whatsapp/videos/{timestamp}_{filename}"
2. WHEN a video is successfully uploaded to R2 THEN the WhatsApp Media System SHALL return a public URL for accessing the video
3. WHEN a video upload fails THEN the WhatsApp Media System SHALL return an error response with failure details
4. WHEN storing video metadata THEN the WhatsApp Media System SHALL save the R2 URL in the message content

### Requirement 6

**User Story:** As a developer, I want a unified media storage service, so that all media types use consistent upload logic.

#### Acceptance Criteria

1. WHEN any media file is uploaded THEN the WhatsApp Media System SHALL use a single service class to handle the upload process
2. WHEN uploading media THEN the WhatsApp Media System SHALL sanitize filenames by removing special characters
3. WHEN uploading media THEN the WhatsApp Media System SHALL generate unique filenames using timestamp prefix to prevent collisions
4. WHEN uploading media THEN the WhatsApp Media System SHALL set appropriate content-type headers based on file MIME type

### Requirement 7

**User Story:** As a system administrator, I want to switch between local and R2 storage, so that I can choose the appropriate storage backend.

#### Acceptance Criteria

1. WHEN FILESYSTEM_DISK environment variable is set to "r2" THEN the WhatsApp Media System SHALL use R2 storage for all media uploads
2. WHEN FILESYSTEM_DISK environment variable is set to "public" or not set THEN the WhatsApp Media System SHALL use local storage for all media uploads
3. WHEN switching storage backends THEN the WhatsApp Media System SHALL maintain the same URL format in message content
