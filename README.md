# Laravel Backend API

A clean, minimal Laravel 12 backend API project - easy to understand and maintain for new Laravel developers.

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (default) or your preferred database

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

2. **Start the development server**
   ```bash
   composer run dev
   ```
   The API will be available at `http://localhost:8000`

## 📁 Project Structure

```
app/
├── Http/Controllers/    # Your API controllers
├── Models/              # Database models (Eloquent)
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

### Your API Routes
Add your routes in `routes/api.php`. All routes here are automatically prefixed with `/api`.

**Example:**
```php
// In routes/api.php
Route::get('/posts', [PostController::class, 'index']);
```
This becomes: `GET /api/posts`

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

DB_CONNECTION=sqlite
# Or use MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=your_database
# DB_USERNAME=your_username
# DB_PASSWORD=your_password
```

## 📚 Learning Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Pest Testing](https://pestphp.com/docs)
- [Laravel API Resources](https://laravel.com/docs/12.x/eloquent-resources)
- [Laravel Validation](https://laravel.com/docs/12.x/validation)

## 🎯 Best Practices

1. **Always use Form Requests** for validation
2. **Use API Resources** to format JSON responses
3. **Write tests** for your endpoints
4. **Run Pint** before committing code
5. **Use Eloquent relationships** instead of manual joins
6. **Keep controllers thin** - move logic to services or actions

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
