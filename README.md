# MONET API - Money Expense Tracker

[![PHP](https://img.shields.io/badge/PHP-8.1+-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10.x-red)](https://laravel.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange)](https://mysql.com)

A comprehensive personal finance management system built with Laravel. MONET provides a robust API for expense tracking, budget management, multi-currency support, and advanced financial analytics with both user and admin interfaces.

## 🎯 Core Features

### Financial Management
- **Transaction Tracking**: Complete income and expense management with categorization
- **Multi-Currency Support**: Handle different currencies with exchange rate conversion
- **Account Management**: Multiple account types (General, Cash, Mobile Money, Savings, Current, etc.)
- **Budget Management**: Create, monitor, and get notifications for budget limits
- **Transfer System**: Money transfers between accounts with currency conversion support
- **Financial Analytics**: Detailed statistics, trends, and reporting

### Authentication & Security
- **Google OAuth Integration**: Seamless sign-in with Google accounts
- **Traditional Authentication**: Email/password with OTP verification
- **JWT Token Authentication**: Secure API access with Laravel Sanctum
- **Password Reset**: OTP-based password recovery system
- **Session Management**: Real-time session monitoring and control

### Administration
- **Admin Dashboard**: Comprehensive management interface
- **User Management**: Monitor and manage user accounts
- **Session Monitoring**: Track active sessions with device detection
- **System Settings**: Configuration management and system maintenance
- **Database Management**: Admin tools for data management

## 🏗️ System Architecture

### Database Schema

#### Core Tables
- **users**: User accounts with Google OAuth support
- **accounts**: User financial accounts with currency and type
- **transactions**: Income/expense/transfer records
- **categories**: Transaction categorization (income/expense/transfer)
- **budgets**: Budget management with notification thresholds
- **currencies**: Multi-currency support with formatting
- **account_types**: Account classification system

#### Authentication & Admin
- **admins**: Administrative user accounts
- **personal_access_tokens**: API token management
- **sessions**: User session tracking
- **otps**: One-time password verification
- **password_resets**: Password recovery tokens

#### Transfer System
- **transfers**: Inter-account money transfers with exchange rates
- **notifications**: Budget alerts and system notifications

### Account Types Available
- General Accounts
- Cash Accounts  
- Mobile Money (MOMO)
- Savings Account
- Current Account
- Credit Card
- Investment Account
- Loan Account

## 🔧 Installation & Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL 8.0 or higher
- Node.js & NPM (for frontend assets)

### Installation Steps

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd monet
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Configuration**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=monet_db
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Google OAuth Setup**
   ```env
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   ```

6. **Database Migration & Seeding**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Build Assets**
   ```bash
   npm run build
   ```

8. **Start Development Server**
   ```bash
   php artisan serve
   ```

## 🔐 Authentication System

### Google OAuth Integration
```http
POST /api/google-signup      # Register new users with Google
POST /api/google-login       # Login existing users with Google  
POST /api/google-auth        # Universal Google auth (handles both)
```

### Traditional Authentication
```http
POST /api/register           # User registration
POST /api/login              # User login
POST /api/logout             # User logout
POST /api/reset/otp          # Request password reset OTP
POST /api/reset/password     # Reset password with OTP
```

### Profile Management
```http
GET  /api/profile            # Get user profile
PUT  /api/profile            # Update profile
PUT  /api/profile/password   # Change password
```

### OTP System
```http
POST /api/otp                # Generate OTP
POST /api/verify             # Verify OTP
```

## 📊 API Endpoints

### Accounts Management
```http
GET    /api/account          # List user accounts
GET    /api/account/{id}     # Get account details
POST   /api/account          # Create new account
PATCH  /api/account/{id}     # Update account
DELETE /api/account/{id}     # Delete account
```

### Transaction Management
```http
GET    /api/transaction                    # List transactions with filters
GET    /api/transaction/statistics         # Get transaction statistics
GET    /api/transaction/{uuid}             # Get transaction details
POST   /api/transaction                    # Create transaction
PUT    /api/transaction/{uuid}             # Update transaction
DELETE /api/transaction/{uuid}             # Delete transaction
POST   /api/transaction/transfer           # Transfer between accounts
POST   /api/transaction/currency-transfer  # Multi-currency transfer
GET    /api/exchange-rate                  # Get exchange rates
```

### Category Management
```http
GET    /api/category                       # List categories
GET    /api/category/{uuid}                # Get category details
GET    /api/category/{uuid}/transactions   # Get category transactions
GET    /api/category/{uuid}/statistics     # Get category statistics
POST   /api/category                       # Create category
PUT    /api/category/{uuid}                # Update category
DELETE /api/category/{uuid}                # Delete category
```

### Budget Management
```http
GET    /api/budget                         # List budgets
GET    /api/budget/statistics              # Get budget statistics
GET    /api/budget/{uuid}                  # Get budget details
GET    /api/budget/{uuid}/performance      # Get budget performance
POST   /api/budget                         # Create budget
PUT    /api/budget/{uuid}                  # Update budget
DELETE /api/budget/{uuid}                  # Delete budget
```

### Currency & Account Types
```http
GET    /api/currency                       # List currencies
GET    /api/currency/{id}                  # Get currency details
POST   /api/currency                       # Create currency
PUT    /api/currency/{uuid}                # Update currency
DELETE /api/currency/{uuid}                # Delete currency

GET    /api/account-type                   # List account types
GET    /api/account-type/{id}              # Get account type details
```

### Notifications
```http
GET    /api/notification                   # List notifications
GET    /api/notification/unread-count      # Get unread count
PUT    /api/notification/{uuid}/read       # Mark as read
PUT    /api/notification/mark-all-read     # Mark all as read
DELETE /api/notification/{uuid}            # Delete notification
DELETE /api/notification/delete-all        # Delete all notifications
```

## 🛡️ Admin Panel

Access the admin panel at: `http://localhost:8000/admin`

### Admin Features
- **Dashboard**: System overview and metrics
- **User Management**: View and manage user accounts
- **Transaction Management**: Monitor all transactions across users
- **Account Management**: Oversee all user accounts
- **Session Management**: Real-time session monitoring
- **System Settings**: Configuration and maintenance tools

### Admin Routes
```http
GET    /admin/dashboard                    # Admin dashboard
GET    /admin/users                       # User management
GET    /admin/transactions                # Transaction management
GET    /admin/accounts                    # Account management
GET    /admin/sessions                    # Session management
GET    /admin/settings                    # System settings
```

## 💰 Transaction System

### Transaction Types
- **Income**: Money received (salary, business income, gifts)
- **Expense**: Money spent (food, transportation, bills)
- **Transfer**: Money moved between accounts

### Transfer Features
- **Same Currency**: Direct transfers between accounts
- **Multi-Currency**: Transfers with automatic exchange rate conversion
- **Real-time Rates**: Option to use current exchange rates
- **Transfer History**: Complete audit trail of all transfers

### Example Transfer Request
```json
{
    "source_account_id": "account-uuid-1",
    "destination_account_id": "account-uuid-2", 
    "amount": 100.00,
    "destination_amount": 85.50,
    "description": "Monthly savings transfer",
    "transaction_date": "2024-01-15",
    "use_real_time_rate": true
}
```

## 📈 Budget System

### Budget Features
- **Category-based**: Budgets linked to expense categories
- **Time Periods**: Daily, weekly, monthly, quarterly, yearly budgets
- **Threshold Alerts**: Configurable notification thresholds (default 80%)
- **Automatic Tracking**: Real-time spent amount calculation
- **Status Management**: Active, inactive, completed, exceeded statuses

### Budget Notifications
- Automatic notifications when spending reaches threshold
- Budget exceeded alerts
- Budget period completion notifications

## 🌍 Multi-Currency Support

### Currency Features
- **Multiple Currencies**: Support for various world currencies
- **Exchange Rates**: Real-time rate conversion for transfers
- **Currency Formatting**: Proper symbol positioning and decimal places
- **Account-specific**: Each account can have different currency

### Supported Currency Fields
- Name, Code, Symbol
- Symbol Position (before/after amount)
- Thousand & Decimal Separators
- Decimal Places Configuration

## 📱 Mobile App Integration

### API Design
- RESTful API architecture
- JWT token authentication
- UUID-based resource identification
- Comprehensive error handling
- Pagination support

### Response Format
```json
{
    "status": "success",
    "message": "Operation completed successfully",
    "data": {
        // Response data
    },
    "meta": {
        // Pagination info when applicable
    }
}
```

## 🔧 Development

### Database Seeding
The system includes comprehensive seeders:
- **CategorySeeder**: Pre-defined income/expense categories
- **CurrencySeeder**: Common world currencies
- **AccountTypeSeeder**: Standard account types
- **AdminSeeder**: Default admin account

### Artisan Commands
```bash
php artisan budget:check-notifications    # Check budget thresholds
php artisan migrate:fresh --seed          # Fresh installation with data
```

### Testing
```bash
php artisan test                          # Run test suite
```

## 🚀 Deployment

### Production Requirements
- PHP 8.1+ with required extensions
- MySQL 8.0+ or compatible database
- Web server (Nginx/Apache)
- SSL certificate for HTTPS
- Redis (recommended for sessions/cache)

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=monet_production

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password

# Google OAuth
GOOGLE_CLIENT_ID=production-client-id
GOOGLE_CLIENT_SECRET=production-client-secret
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📞 Support

For support and questions:
- Check the documentation in the `docs/` folder
- Report issues on GitHub Issues
- Contact the development team

---

**MONET API - Making personal finance management simple and powerful.**
