# SplashEstate CRM

A complete multi-tenant Real Estate CRM SaaS system built with pure PHP and MySQL. No frameworks, lightweight, and beginner-friendly.

## Features

- **Multi-tenant Architecture** - Complete tenant isolation with subscription management
- **Lead Management** - Capture, track, and convert leads
- **Client Management** - Manage buyers, sellers, landlords, and tenants
- **Property Listings** - Residential, commercial, and land properties
- **Deal Pipeline** - Kanban-style deal tracking
- **Task Management** - Assign and track tasks with reminders
- **Activity Tracking** - Calls, meetings, emails, and notes
- **File Attachments** - Secure file uploads with validation
- **Subscription System** - Plans, quotas, and billing
- **REST API** - Public API for lead generation
- **Role-based Access** - Platform admin, tenant admin, and agents
- **Responsive Design** - Clean, mobile-friendly interface

## Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- PHP Extensions: PDO, pdo_mysql, mbstring

## Installation

### 1. Clone or Download

```bash
git clone https://github.com/yourusername/SplashEstateCRM.git
cd SplashEstateCRM
```

### 2. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` and update your database credentials:

```env
DB_HOST=localhost
DB_NAME=splashestate_crm
DB_USER=root
DB_PASS=your_password
BASE_URL=http://localhost/SplashEstateCRM/public
```

### 3. Create Database

Create a new MySQL database:

```sql
CREATE DATABASE splashestate_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the database schema:

```bash
mysql -u root -p splashestate_crm < database.sql
```

### 4. Set Permissions

Make the storage directory writable:

```bash
chmod -R 755 storage/
mkdir -p storage/uploads
chmod -R 777 storage/uploads
```

### 5. Configure Apache

Ensure your Apache configuration allows `.htaccess` overrides:

```apache
<Directory "/var/www/html/SplashEstateCRM">
    AllowOverride All
    Require all granted
</Directory>
```

Restart Apache:

```bash
sudo service apache2 restart
```

### 6. Access the Application

Open your browser and navigate to:

```
http://localhost/SplashEstateCRM/public
```

## Default Credentials

### Platform Admin
- **Email:** admin@splashestate.com
- **Password:** admin123

### Demo Tenant 1 (Sunset Realty Group)
- **Email:** john.smith@sunsetrealty.com
- **Password:** agent123
- **API Key:** `sk_test_4eC39HqLyjWDarjtT1zdp7dc11111111111111111111111111111111`

### Demo Tenant 2 (Metro Properties Inc)
- **Email:** lisa.chen@metroproperties.com
- **Password:** agent123
- **API Key:** `sk_test_7uH92KfDjkPNxywqR6vbm3ef22222222222222222222222222222222`

## REST API Documentation

### Authentication

All API requests require an `X-API-KEY` header with your tenant's API key.

### Create Lead

**Endpoint:** `POST /api/leads/create`

**Headers:**
```
Content-Type: application/json
X-API-KEY: your_api_key_here
```

**Request Body:**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "(555) 123-4567",
    "interest_type": "buy",
    "budget_min": 400000,
    "budget_max": 500000,
    "notes": "Looking for a 3-bedroom house"
}
```

**Success Response (201 Created):**
```json
{
    "success": true,
    "message": "Lead created successfully",
    "data": {
        "lead_id": 123,
        "lead": {
            "id": "123",
            "tenant_id": "1",
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "phone": "(555) 123-4567",
            "source": "API",
            "status": "new",
            "interest_type": "buy",
            "budget_min": "400000.00",
            "budget_max": "500000.00",
            "notes": "Looking for a 3-bedroom house",
            "created_at": "2025-01-22 10:30:00"
        }
    }
}
```

**Error Response (401 Unauthorized):**
```json
{
    "error": "Invalid API key"
}
```

**Error Response (422 Validation Error):**
```json
{
    "error": "Validation failed",
    "errors": {
        "first_name": "First name is required",
        "email": "Invalid email format"
    }
}
```

**Error Response (429 Too Many Requests):**
```json
{
    "error": "Lead limit reached. Please upgrade your plan."
}
```

### List Leads

**Endpoint:** `GET /api/leads/index`

**Headers:**
```
X-API-KEY: your_api_key_here
```

**Query Parameters:**
- `page` (optional) - Page number (default: 1)
- `per_page` (optional) - Records per page (default: 20, max: 100)
- `status` (optional) - Filter by status (new, contacted, qualified, etc.)

**Example Request:**
```
GET /api/leads/index?page=1&per_page=20&status=new
X-API-KEY: sk_test_4eC39HqLyjWDarjtT1zdp7dc11111111111111111111111111111111
```

**Success Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "leads": [
            {
                "id": "1",
                "first_name": "John",
                "last_name": "Doe",
                "email": "john.doe@example.com",
                "status": "new",
                "created_at": "2025-01-22 10:30:00"
            }
        ],
        "pagination": {
            "page": 1,
            "per_page": 20,
            "total": 45,
            "total_pages": 3
        }
    }
}
```

### cURL Examples

**Create Lead:**
```bash
curl -X POST http://localhost/SplashEstateCRM/public/api/leads/create \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: sk_test_4eC39HqLyjWDarjtT1zdp7dc11111111111111111111111111111111" \
  -d '{
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane@example.com",
    "phone": "(555) 987-6543",
    "interest_type": "sell",
    "notes": "Wants to sell 4-bedroom house"
  }'
```

**List Leads:**
```bash
curl -X GET "http://localhost/SplashEstateCRM/public/api/leads/index?page=1&status=new" \
  -H "X-API-KEY: sk_test_4eC39HqLyjWDarjtT1zdp7dc11111111111111111111111111111111"
```

## Architecture

### Directory Structure

```
SplashEstateCRM/
├── app/
│   ├── controllers/        # Controllers for all modules
│   │   ├── api/           # API controllers
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── LeadsController.php
│   │   ├── ClientsController.php
│   │   ├── PropertiesController.php
│   │   ├── DealsController.php
│   │   └── TasksController.php
│   ├── models/            # Data models
│   │   ├── User.php
│   │   ├── Lead.php
│   │   ├── Client.php
│   │   └── ...
│   ├── views/             # View templates
│   │   ├── layouts/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   └── ...
│   ├── core/              # Core MVC classes
│   │   ├── Router.php
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   ├── View.php
│   │   └── Database.php
│   └── helpers/           # Helper functions
│       └── functions.php
├── config/                # Configuration files
│   └── config.php
├── public/                # Public web root
│   ├── index.php         # Application entry point
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   └── .htaccess
├── storage/              # File uploads
│   └── uploads/
├── tests/                # Test files
├── database.sql          # Database schema with seed data
├── .env.example          # Environment configuration template
├── .htaccess            # Root htaccess
└── README.md
```

### Multi-Tenant Design

Every table includes a `tenant_id` column for data isolation:
- All queries automatically filter by `tenant_id`
- API keys are tied to specific tenants
- File uploads are stored in tenant-specific directories
- Subscription quotas are enforced per tenant

### Security Features

- **CSRF Protection** - Token-based protection for all forms
- **Password Hashing** - bcrypt via `password_hash()`
- **Input Validation** - Server-side validation on all inputs
- **SQL Injection Prevention** - Prepared statements only
- **File Upload Validation** - Type, size, and extension checks
- **XSS Prevention** - Output escaping with `htmlspecialchars()`
- **Session Security** - Secure session management
- **API Key Authentication** - Secure API access

## Subscription Plans

### Default Plans

1. **Free Trial**
   - $0/month
   - 50 leads
   - 10 properties
   - 2 agents

2. **Professional**
   - $49/month
   - 500 leads
   - 100 properties
   - 10 agents

3. **Enterprise**
   - $199/month
   - Unlimited leads
   - Unlimited properties
   - Unlimited agents

### Quota Enforcement

The system automatically enforces quotas:
- Users cannot create new leads/properties when limits are reached
- API requests return 429 error when quota exceeded
- Upgrade prompts displayed in the UI

## Customization

### Adding a New Module

1. Create model in `app/models/YourModel.php`
2. Create controller in `app/controllers/YourController.php`
3. Create views in `app/views/your_module/`
4. Add routes (router handles convention-based routing automatically)

### Changing Colors/Styling

Edit `public/assets/css/style.css` to customize:
- Primary color: `#3498db`
- Secondary color: `#95a5a6`
- Success color: `#27ae60`
- Danger color: `#e74c3c`

## Testing

Run the included test suite:

```bash
php tests/run_tests.php
```

Tests include:
- Authentication tests
- CRUD operations
- Tenant isolation
- Subscription enforcement
- API endpoint tests

## Deployment

### Production Checklist

1. **Environment Configuration**
   - Set `APP_ENV=production` in `.env`
   - Set `APP_DEBUG=false`
   - Use strong database passwords
   - Change all default passwords

2. **Security**
   - Enable HTTPS (SSL certificate)
   - Disable directory listing
   - Set proper file permissions (644 for files, 755 for directories)
   - Remove or restrict access to `database.sql`

3. **Database**
   - Remove seed data in production
   - Set up regular backups
   - Optimize tables and indexes

4. **Performance**
   - Enable PHP opcode caching (OPcache)
   - Configure MySQL query cache
   - Set up CDN for static assets
   - Enable GZIP compression

5. **Monitoring**
   - Set up error logging
   - Monitor disk space for uploads
   - Track API usage
   - Set up uptime monitoring

### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/splashestate/public

    <Directory /var/www/splashestate/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/splashestate_error.log
    CustomLog ${APACHE_LOG_DIR}/splashestate_access.log combined
</VirtualHost>
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/splashestate/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Troubleshooting

### Database Connection Error
- Verify database credentials in `.env`
- Ensure MySQL is running
- Check if database exists

### 404 Errors
- Verify Apache mod_rewrite is enabled
- Check `.htaccess` files exist
- Ensure `AllowOverride All` is set

### File Upload Errors
- Check directory permissions on `storage/uploads`
- Verify `MAX_UPLOAD_SIZE` in `.env`
- Check PHP `upload_max_filesize` and `post_max_size`

### Session Issues
- Ensure session directory is writable
- Check PHP session configuration
- Clear browser cookies

## Support

For issues and questions:
- GitHub Issues: https://github.com/yourusername/SplashEstateCRM/issues
- Documentation: https://docs.splashestate.com
- Email: support@splashestate.com

## License

This project is licensed under the MIT License.

## Credits

Developed by SplashEstate Team
Built with PHP, MySQL, and vanilla JavaScript
