<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWhatsAppDataSharingConsentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_profile_shared' => 'sometimes|boolean',
            'contacts_shared' => 'sometimes|boolean',
            'conversation_history_shared' => 'sometimes|boolean',
            'sharing_mode' => 'sometimes|in:all,selected',
            'selected_contact_ids' => 'nullable|array',
            'selected_contact_ids.*' => 'integer|distinct|exists:whatsapp_contacts,id',
        ];
    }

    public function messages(): array
    {
        return [
            'selected_contact_ids.*.exists' => 'One or more selected chats could not be found.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $conversationShared = (bool) $this->input('conversation_history_shared', true);
            $sharingMode = $this->input('sharing_mode');
            $selectedContactIds = $this->input('selected_contact_ids', []);

            if ($conversationShared && $sharingMode === 'selected' && empty($selectedContactIds)) {
                $validator->errors()->add('selected_contact_ids', 'Select at least one chat when using selected chat mode.');
            }
        });
    }
}
