<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update existing messages structure from 'role' to 'type' and map values:
     * - 'user' -> 'human'
     * - 'assistant' -> 'ai'
     */
    public function up(): void
    {
        // Get all conversations with messages
        $conversations = DB::table('ai_agent_conversations')
            ->whereNotNull('messages')
            ->get();

        foreach ($conversations as $conversation) {
            $messages = json_decode($conversation->messages, true);

            if (! is_array($messages)) {
                continue;
            }

            $updatedMessages = [];
            foreach ($messages as $message) {
                // Skip if already using 'type'
                if (isset($message['type'])) {
                    $updatedMessages[] = $message;

                    continue;
                }

                // Map 'role' to 'type'
                $type = match ($message['role'] ?? 'user') {
                    'user' => 'human',
                    'assistant' => 'ai',
                    default => 'human'
                };

                $updatedMessages[] = [
                    'type' => $type,
                    'content' => $message['content'] ?? '',
                    'timestamp' => $message['timestamp'] ?? now()->toIso8601String(),
                ];
            }

            // Update the conversation
            DB::table('ai_agent_conversations')
                ->where('id', $conversation->id)
                ->update(['messages' => json_encode($updatedMessages)]);
        }
    }

    /**
     * Reverse the migrations.
     * Revert 'type' back to 'role' and map values:
     * - 'human' -> 'user'
     * - 'ai' -> 'assistant'
     */
    public function down(): void
    {
        // Get all conversations with messages
        $conversations = DB::table('ai_agent_conversations')
            ->whereNotNull('messages')
            ->get();

        foreach ($conversations as $conversation) {
            $messages = json_decode($conversation->messages, true);

            if (! is_array($messages)) {
                continue;
            }

            $updatedMessages = [];
            foreach ($messages as $message) {
                // Skip if already using 'role'
                if (isset($message['role'])) {
                    $updatedMessages[] = $message;

                    continue;
                }

                // Map 'type' to 'role'
                $role = match ($message['type'] ?? 'human') {
                    'human' => 'user',
                    'ai' => 'assistant',
                    default => 'user'
                };

                $updatedMessages[] = [
                    'role' => $role,
                    'content' => $message['content'] ?? '',
                    'timestamp' => $message['timestamp'] ?? now()->toIso8601String(),
                ];
            }

            // Update the conversation
            DB::table('ai_agent_conversations')
                ->where('id', $conversation->id)
                ->update(['messages' => json_encode($updatedMessages)]);
        }
    }
};
