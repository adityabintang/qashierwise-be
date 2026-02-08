# Quick Start Guide - AI Agent Optimization

## 🚀 Setup (5 menit)

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
```

### 3. Test
```bash
# Test dengan existing endpoint
POST /api/ai-agent/test
Authorization: Bearer {your_token}
Content-Type: application/json

{
    "message": "pesan dimsum 2 dan teh jumbo 1"
}
```

## ✅ Verifikasi

### Check Database
```sql
-- Verify new columns
SELECT id, bot_name, use_optimized_prompt, product_sample_limit 
FROM ai_agents;

-- Should show:
-- use_optimized_prompt = 1 (default enabled)
-- product_sample_limit = 10 (default)
```

### Check Logs
```bash
# Monitor logs untuk melihat intent detection
tail -f storage/logs/laravel.log | grep "Intent detected"
```

## 🎯 Usage Examples

### Example 1: Greeting (Optimized)
```
User: "Halo"
Intent: GREETING
Prompt includes: Business info only
Prompt excludes: Product list, ordering workflow
Token savings: ~30%
```

### Example 2: View Menu
```
User: "menunya apa aja?"
Intent: VIEW_MENU
Prompt includes: Product samples
Prompt excludes: Ordering workflow details
Token savings: ~10%
```

### Example 3: Order
```
User: "pesan dimsum 2"
Intent: ORDER
Prompt includes: Ordering workflow, anti-hallucination rules
Prompt excludes: Unnecessary business info
Token savings: ~5%
```

## 🔧 Configuration

### Disable Optimization (per agent)
```php
$aiAgent = AiAgent::find(1);
$aiAgent->use_optimized_prompt = false;
$aiAgent->save();
```

### Adjust Product Sample Limit
```php
$aiAgent->product_sample_limit = 20; // Default: 10
$aiAgent->save();
```

### Customize Prompt Rules
Edit `config/ai_agent_prompts.php`:
```php
'core_rules' => "
## ATURAN INTI:
1. Custom rule here...
",
```

## 📊 Monitor Performance

### Check Token Savings
```php
use App\Services\AiPromptAnalytics;

$savings = AiPromptAnalytics::getTokenSavings($agentId);
echo "Token savings: {$savings['savings_percent']}%";
```

### View Analytics
```sql
SELECT 
    prompt_type,
    AVG(tokens_used) as avg_tokens,
    COUNT(*) as usage_count
FROM ai_prompt_analytics
WHERE ai_agent_id = 1
GROUP BY prompt_type;
```

## 🐛 Troubleshooting

### Issue: Optimization not working
**Solution:**
```php
// Check if enabled
$agent = AiAgent::find(1);
dd($agent->use_optimized_prompt); // Should be true

// Check config loaded
dd(config('ai_agent_prompts.core_rules')); // Should show rules
```

### Issue: Product IDs showing to user
**Solution:**
```php
use App\Services\AiResponseValidator;

$validator = new AiResponseValidator();
$cleanResponse = $validator->sanitize($response);
```

### Issue: Intent not detected
**Solution:**
```php
use App\Enums\UserIntent;

$intent = UserIntent::detect("pesan dimsum");
dd($intent); // Should be UserIntent::ORDER
```

## 📚 Key Files

- `app/Services/AiAgentPromptBuilder.php` - Prompt builder
- `app/Services/AiResponseValidator.php` - Response validator
- `app/Enums/UserIntent.php` - Intent detection
- `config/ai_agent_prompts.php` - Prompt templates
- `app/Models/AiAgent.php` - Updated model

## ✨ Features

✅ Intent-based prompt optimization
✅ Automatic response validation
✅ Product ID sanitization
✅ Token usage analytics
✅ Backward compatible
✅ Zero breaking changes

## 🎉 Done!

Sistem sudah siap digunakan. Optimasi akan berjalan otomatis untuk semua AI agents.
