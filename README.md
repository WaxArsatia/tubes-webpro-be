# Laravel Backend API

A clean, minimal Laravel 12 backend API project with **Google Gemini AI integration** - easy to understand and maintain for new Laravel developers.

## ✨ Features

- 🤖 **AI-Powered**: Document summarization and quiz generation using Google Gemini AI
- 📄 **Document Management**: Upload, view, and manage PDF documents
- 📝 **Smart Summaries**: Generate 4 types of summaries (concise, detailed, bullet points, abstract)
- 🎯 **Quiz Generation**: AI-generated quizzes with configurable difficulty levels
- 🔐 **Authentication**: Laravel Sanctum API authentication
- 👥 **User Management**: Admin and user roles with authorization
- 📊 **Activity Tracking**: Comprehensive user activity logging
- ✅ **100% Test Coverage**: 130+ passing tests with Pest

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (default) or your preferred database
- **Google Gemini API Key** (get from [Google AI Studio](https://aistudio.google.com/app/apikey))

### Installation

1. **Clone and setup**
   ```bash
   composer run setup
   ```
   This will:
   - Install dependencies
   - Create `.env` file
   - Generate application key
   - Run database migrations

2. **Configure environment**
   - Open `.env` and add your Gemini API key:
   ```env
   GEMINI_API_KEY=your_api_key_here
   ```

3. **Start the development server**
   ```bash
   composer run dev
   ```
   The API will be available at `http://localhost:8000`

## 📁 Project Structure

```
app/
├── Http/Controllers/    # Your API controllers
├── Models/              # Database models (Eloquent)
├── Services/            # Business logic (e.g., GeminiService for AI)
└── Providers/           # Service providers

routes/
├── api.php             # API routes (prefixed with /api)
└── web.php             # Web routes (for health checks)

database/
├── migrations/         # Database table definitions
├── factories/          # Model factories for testing
└── seeders/            # Database seeders

tests/
├── Feature/            # Feature tests (test complete flows)
└── Unit/               # Unit tests (test individual methods)
```

## 🛠️ Common Commands

### Development
```bash
composer run dev          # Start development server
php artisan serve         # Alternative way to start server
```

### Database
```bash
php artisan migrate       # Run migrations
php artisan migrate:fresh # Fresh database (WARNING: deletes all data)
php artisan db:seed       # Run seeders
```

### Testing
```bash
composer run test         # Run all tests
php artisan test          # Alternative way to run tests
php artisan test --filter=ExampleTest  # Run specific test
```

### Code Quality
```bash
vendor/bin/pint           # Format code (follows Laravel standards)
```

### Creating New Files
```bash
# Models
php artisan make:model Post -mfs
# -m creates migration, -f creates factory, -s creates seeder

# Controllers
php artisan make:controller PostController --api
# --api creates controller with API methods (index, store, show, update, destroy)

# Migrations
php artisan make:migration create_posts_table

# Tests
php artisan make:test PostTest --pest
```

## 🔌 API Endpoints

### Health Check
- `GET /` - Returns API status
- `GET /up` - Laravel health check
- `GET /api/health` - API health endpoint

### Authentication
- `POST /api/register` - Register new user
- `POST /api/login` - Login and get token
- `POST /api/logout` - Logout (requires auth)
- `GET /api/profile` - Get user profile (requires auth)

### Documents
- `POST /api/documents` - Upload PDF document
- `GET /api/documents` - List user's documents
- `GET /api/documents/{id}` - Get document details
- `GET /api/documents/{id}/download` - Download document
- `DELETE /api/documents/{id}` - Delete document

### AI-Powered Summaries
- `POST /api/summaries/generate` - Generate AI summary from document
- `GET /api/summaries` - List all summaries
- `GET /api/summaries/{id}` - Get specific summary
- `GET /api/documents/{id}/summaries` - Get summaries for a document
- `DELETE /api/summaries/{id}` - Delete summary

### AI-Powered Quizzes
- `POST /api/quizzes/generate` - Generate AI quiz from document
- `GET /api/quizzes` - List all quizzes
- `GET /api/quizzes/{id}` - Get quiz details
- `POST /api/quizzes/{id}/start` - Start quiz attempt
- `POST /api/quizzes/{id}/submit` - Submit quiz answers
- `GET /api/quizzes/{quiz_id}/attempts/{attempt_id}` - Get attempt results
- `DELETE /api/quizzes/{id}` - Delete quiz

### Activity History
- `GET /api/history` - Get user activity history
- `GET /api/documents/{id}/activities` - Get document activities
- `GET /api/history/stats` - Get activity statistics
- `DELETE /api/history` - Clear activity history

### Admin (Admin role required)
- `GET /api/admin/dashboard` - Admin dashboard stats
- `GET /api/admin/users` - List all users
- `GET /api/admin/users/{id}` - Get user details
- `PUT /api/admin/users/{id}` - Update user
- `DELETE /api/admin/users/{id}` - Delete user

**📚 Detailed API Documentation:**
- [Authentication API](./AUTH_API_SPEC.md)
- [Document API](./DOCUMENT_API_SPEC.md)
- [Summarization API](./SUMMARIZATION_API_SPEC.md) - AI Integration Details
- [Quiz API](./QUIZ_API_SPEC.md) - AI Integration Details
- [History API](./HISTORY_API_SPEC.md)
- [Profile API](./PROFILE_API_SPEC.md)
- [Admin API](./ADMIN_API_SPEC.md)

## 🤖 AI Integration

This project uses **Google Gemini AI** for document processing:

### Summary Generation
- **Model**: `gemini-2.0-flash`
- **Service**: `App\Services\GeminiService`
- **Types**: Concise, Detailed, Bullet Points, Abstract
- **Process**: 
  1. Upload PDF to Gemini File API
  2. AI analyzes document content
  3. Generate custom summary based on type
  4. Clean up uploaded file

### Quiz Generation
- **Model**: `gemini-2.0-flash`
- **Structured Output**: JSON schema validation
- **Difficulty Levels**: Easy, Medium, Hard
- **Question Types**: Multiple choice, True/false, Mixed
- **Features**:
  - Questions with 4 options each
  - Correct answers with explanations
  - Mixed answer positioning
  - Content coverage across document

### Configuration
```env
# Required
GEMINI_API_KEY=your_api_key_here

# Optional (defaults shown)
GEMINI_REQUEST_TIMEOUT=30
```

Get your API key from [Google AI Studio](https://aistudio.google.com/app/apikey).

---

## 📝 Creating Your First API Endpoint

1. **Create a controller**
   ```bash
   php artisan make:controller Api/PostController --api
   ```

2. **Add routes** in `routes/api.php`:
   ```php
   use App\Http\Controllers\Api\PostController;
   
   Route::apiResource('posts', PostController::class);
   ```

3. **Implement controller methods** in `app/Http/Controllers/Api/PostController.php`

4. **Test your endpoint**
   ```bash
   curl http://localhost:8000/api/posts
   ```

## 🧪 Testing

This project uses **Pest** for testing (simpler than PHPUnit).

**Example test** in `tests/Feature/PostTest.php`:
```php
it('can list all posts', function () {
    $response = $this->getJson('/api/posts');
    
    $response->assertSuccessful();
});
```

Run tests:
```bash
composer run test
```

## 🔐 Environment Configuration

Copy `.env.example` to `.env` and configure:

```env
APP_NAME="Your API Name"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
# Or use MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=your_database
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Google Gemini AI (Required for AI features)
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_REQUEST_TIMEOUT=30
```

## 📚 Learning Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Pest Testing](https://pestphp.com/docs)
- [Laravel API Resources](https://laravel.com/docs/12.x/eloquent-resources)
- [Laravel Validation](https://laravel.com/docs/12.x/validation)
- [Google Gemini AI Documentation](https://ai.google.dev/gemini-api/docs)
- [Gemini PHP Laravel Package](https://github.com/google-gemini-php/laravel)

## 🎯 Best Practices

1. **Always use Form Requests** for validation
2. **Use API Resources** to format JSON responses
3. **Write tests** for your endpoints
4. **Run Pint** before committing code
5. **Use Eloquent relationships** instead of manual joins
6. **Keep controllers thin** - move logic to services or actions
7. **Mock external services** (like GeminiService) in tests
8. **Handle AI errors gracefully** - AI services can be unavailable

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
