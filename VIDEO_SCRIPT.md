# Sweetoria - Web Development 2 Final Examination
## Screen Recording Script (10-20 minutes)

---

## PART 1: INTRODUCTION (1-2 minutes)
### Opening
**[Look at camera, speak clearly and professionally]**

"Good [morning/afternoon]. My name is [YOUR NAME], and I'm in [SECTION/COURSE - e.g., 'Web Development 2, Section A']. 

Today, I'm presenting **Sweetoria** – a modern, full-stack web application for an online bakery and cake ordering system. This project demonstrates professional deployment practices, complete CRUD functionality, user authentication, and a comprehensive admin dashboard.

The application is built with Symfony 7.3, includes both customer-facing and administrative features, and is currently hosted and live at [YOUR HOSTED URL].

Let me walk you through the development process, source code, and deployment."

**[TIMING: Pause for 10 seconds, let it sink in]**

---

## PART 2: SOURCE CODE OVERVIEW (3-4 minutes)

### 2A. Project Structure Introduction
**[Open VS Code - Show the workspace folder structure]**

"Let's start with the project structure. Here's the root directory of Sweetoria..."

**[Highlight these key folders as you explain:]**

```
📁 src/                    → Core application code
📁 templates/              → Twig template files  
📁 config/                 → Configuration files (routes, services, bundles)
📁 migrations/             → Database migrations (30+ versions)
📁 public/                 → Web-accessible files (CSS, images, JavaScript)
📁 assets/                 → Webpack asset sources (JavaScript, SCSS)
📁 tests/                  → Unit and functional tests
📁 docker/                 → Docker configuration for containerization
```

"The application is structured following Symfony best practices with clear separation of concerns."

---

### 2B. Show Database Entities
**[Navigate to src/Entity and open the files]**

"Let me show you the core database entities that power Sweetoria..."

**[Open each entity file briefly and explain:]**

**User Entity** (src/Entity/User.php)
- Manages customer and staff accounts
- Supports OAuth2 Google authentication
- Stores user roles (ROLE_USER, ROLE_ADMIN, ROLE_STAFF)
- Handles email verification

**[Say:] "Users can register, log in with Google, and have different roles for different access levels."**

**Product & Category Entities**
- Product: Represents bakery items (cakes, pastries, etc.)
- Category: Organizes products by type
- Includes pricing, descriptions, and inventory

**[Say:] "Our product catalog is managed through a database relationship where multiple products belong to a category."**

**Order & OrderItem Entities**
- Order: Customer orders with status tracking
- OrderItem: Individual items within an order
- Tracks timestamps and order history

**[Say:] "This allows us to track complete customer orders with multiple items, quantities, and pricing."**

**CakeCustomization Entity**
- Allows customers to customize cake orders
- Stores customization preferences and add-ons
- Links to orders

**[Say:] "A unique feature – customers can customize their cake orders with specific requirements."**

**MenuItem Entity**
- Menu items for display on the website
- Separate from products for content management

**ActivityLog Entity**
- Tracks all system activities for audit purposes
- Records user actions, changes, and admin operations

**[Say:] "We maintain an activity log for security and debugging purposes."**

---

### 2C. Controllers and Features
**[Navigate to src/Controller, show the list of controllers]**

"Here are the main controllers that handle the application logic..."

**Frontend Controllers:**
- **HomeController**: Landing page and homepage
- **MenuController**: Displays menu items
- **ProductController**: Product browsing and details
- **CartController**: Shopping cart operations
- **OrderController**: Customer order creation and tracking
- **CakeCustomizationController**: Customization interface
- **RegistrationController**: User registration
- **LoginController**: User authentication
- **GoogleController**: OAuth2 integration
- **ProfileController**: User profile management
- **VerificationController**: Email verification

**Admin/Staff Controllers:**
- **AdminController**: Main admin dashboard
- **AdminStaffController**: Staff management interface
- **ActivityLogController**: View audit logs

**API Controllers:**
- **CustomerMobileController**: REST API for mobile apps

**[Say:] "The application serves both web customers and mobile apps through a REST API, with dedicated admin and staff interfaces."**

---

### 2D. Environment Configuration
**[Open .env.example file and show the configuration]**

"Let me show you how the application is configured for different environments..."

**[Highlight these sections and explain:]**

```env
# Database Configuration
DATABASE_URL="mysql://user:password@host:port/database?serverVersion=8.0"

# Security
APP_SECRET=your-secret-key-here

# Mailer Configuration  
MAILER_DSN=smtp://user:password@smtp-relay.brevo.com:587
MAILER_FROM_ADDRESS=notifications@sweetoria.com
MAILER_FROM_NAME=Sweetoria

# Google OAuth
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
```

**[Say:] "These environment variables are crucial for production. They're never committed to git – only developers and the hosting platform have access to production values. This keeps sensitive data secure."**

---

### 2E. Important Symfony Components
**[Open config/bundles.php and explain]**

"Sweetoria uses several key Symfony bundles and libraries:

- **Doctrine ORM**: Object-relational mapping for database operations
- **API Platform**: Building REST APIs automatically from entities
- **EasyAdmin**: Admin panel for managing entities
- **Symfony Security**: Authentication, authorization, and RBAC
- **Symfony Validator**: Form and data validation
- **Symfony Mailer**: Email notifications
- **OAuth2 Client Bundle**: Google authentication integration
- **CORS Bundle**: Handling cross-origin requests for APIs
- **Webpack Encore**: Bundling assets for production

These tools work together to create a professional, secure, and scalable application."

---

## PART 3: GITHUB REPOSITORY (2-3 minutes)

### 3A. Repository Overview
**[Open GitHub.com and navigate to your repository]**

"Now let's look at the GitHub repository where all this code is hosted..."

**[Say while showing:]**

"Here's our GitHub repository for Sweetoria. You can see:
- The repository name and description
- It's a public repository for this demonstration
- It contains the complete source code for the project"

---

### 3B. Commit History
**[Click on Commits tab and scroll through]**

"Looking at the commit history, you can see the development progression:

- Initial setup commits creating the Symfony project
- Entity and database migrations as features were added
- Controller implementations for customer-facing features
- Admin dashboard development
- Bug fixes and improvements
- Deployment configuration and Docker setup

The commits tell the story of building this application from start to finish."

**[Scroll through and point out 5-10 significant commits]**

---

### 3C. Repository Structure
**[Show the repository file tree]**

"The repository is organized with:

✓ src/ - All PHP application code  
✓ templates/ - Twig HTML templates  
✓ config/ - Framework configuration  
✓ migrations/ - Database version control (30+ migrations showing evolution)  
✓ public/ - Web-accessible assets  
✓ Dockerfile - Container configuration for deployment  
✓ docker-compose.yaml - Local development environment  
✓ composer.json - PHP dependencies  
✓ package.json - Node.js dependencies for assets

Everything is version-controlled and ready for collaboration."

---

## PART 4: DEPLOYMENT PROCESS (3-5 minutes)

### 4A. Hosting Platform Overview
**[Open your hosting provider dashboard in browser]**

"I've deployed Sweetoria to [HOSTING PLATFORM - e.g., Heroku, Railway, Render, etc.]. 

The deployment process involved several key steps:"

---

### 4B. Database Configuration
**[Show the database setup on hosting platform]**

"Step 1: Database Configuration

On [HOSTING PLATFORM], I set up a MySQL database with:
- Database name: sweetoria_prod
- Version: MySQL 8.0
- Automatic backups enabled

The DATABASE_URL is configured in the environment variables, and the database is automatically initialized when the application first runs."

---

### 4C. Environment Variables Setup
**[Show environment variables in hosting dashboard]**

"Step 2: Environment Variables

I configured all necessary environment variables on [HOSTING PLATFORM]:

```
APP_ENV=production
APP_SECRET=[auto-generated secret key]
DATABASE_URL=[database connection string]
MAILER_DSN=[email service configuration]
GOOGLE_CLIENT_ID=[from Google Cloud Console]
GOOGLE_CLIENT_SECRET=[from Google Cloud Console]
```

These are kept private and never exposed in the repository. [HOSTING PLATFORM] provides a secure vault for these values."

---

### 4D. GitHub Integration
**[Show the deployment settings]**

"Step 3: GitHub Repository Connection

I connected this GitHub repository to [HOSTING PLATFORM] for continuous deployment:
- Whenever code is pushed to the main branch, [HOSTING PLATFORM] automatically pulls it
- It runs composer install to get dependencies
- It builds assets with Webpack
- It starts the application

This means updates are live within minutes of pushing to GitHub."

---

### 4E. Running Migrations
**[Show migration logs or explain the process]**

"Step 4: Database Migrations

Symfony handles database schema versioning through migrations. When the application starts:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

This command is run automatically, ensuring the database schema matches the current code. We have 30+ migrations that show the evolution of our database schema over development."

---

### 4F. Deployment Challenges (If Applicable)
**[Explain any challenges you encountered]**

"During deployment, I encountered [CHOOSE RELEVANT]

**Challenge 1: [Example: Database initialization]**
- Problem: [Describe the issue]
- Solution: [Explain how you solved it]

**Challenge 2: [Example: Environment variables]**
- Problem: [Describe the issue]  
- Solution: [Explain how you solved it]

**Challenge 3: [Example: Static assets]**
- Problem: [Describe the issue]
- Solution: [Explain how you solved it]

These challenges taught me the importance of [lesson learned]."

---

## PART 5: LIVE APPLICATION DEMONSTRATION (3-5 minutes)

### 5A. Homepage & Navigation
**[Navigate to your live application URL]**

"Now let me show you the deployed application in action. Here's the homepage of Sweetoria at [YOUR_URL]..."

**[Say while showing:]**

"The homepage displays:
- Navigation menu with links to menu, products, orders, and account
- Hero section showcasing our bakery brand
- Featured products or promotions
- Responsive design works on all devices
- Google login option for easy registration"

---

### 5B. Product Browsing & Search
**[Click on Products or Menu section]**

"Customers can browse our product catalog. Let me show the product listing:

- All products are displayed in a grid layout
- Each shows name, description, price, and an image
- Users can click on a product to see detailed information
- Products are organized by categories"

**[Click on a product to show details]**

"When viewing a product, users can see:
- Full description
- Price and availability
- Customer reviews or ratings (if implemented)
- Option to add to cart"

---

### 5C. Shopping Cart & Checkout
**[Add a product to cart and proceed to checkout]**

"Let me add a product to the cart and show the checkout process...

[Show cart page with:]
- Items added with quantities
- Price calculation
- Ability to modify quantities or remove items
- Proceed to checkout button"

**[Start checkout process]**

"The checkout includes:
- Shipping address form
- Billing information
- Payment processing
- Order confirmation"

---

### 5D. Cake Customization Feature
**[Navigate to cake customization if available]**

"A unique feature of Sweetoria is our cake customization system:

[Show customization interface with:]
- Size selection (6-inch, 8-inch, 10-inch, etc.)
- Flavor options
- Filling selections
- Frosting styles
- Add-on options
- Custom message or text
- Real-time price updates

Customers can create exactly the cake they want with live pricing."

---

### 5E. User Account & Order History
**[Log in to a customer account]**

"Let me log in to show the customer account features:

[Show account pages:]
- Profile management
- Order history with status
- Past orders and receipts
- Address book
- Account settings

Customers can track their orders from placement to delivery."

---

### 5F. Admin Dashboard
**[Log in with admin credentials]**

"Now let's look at the admin dashboard. This is where Sweetoria managers control the business..."

**[Show admin interface:]**

Dashboard Overview:
- Key metrics (total orders, revenue, recent activity)
- Recent orders
- Top products
- Sales activity chart
- Staff summary

**[Navigate to different sections]**

Admin Features:
- **Products Management**: Add, edit, delete products
- **Categories Management**: Organize products
- **Orders Management**: View and manage customer orders
- **Users Management**: Manage customer and staff accounts
- **Activity Logs**: Audit trail of all system activities

"The admin panel is built with EasyAdmin, which provides a professional interface for managing the entire business."

---

### 5G. Staff Dashboard
**[Switch to staff view or show staff capabilities]**

"Staff members have a dedicated dashboard with:
- Recent orders to fulfill
- Customer information
- Order status updates
- Task assignments
- Performance metrics

This allows our bakery team to efficiently manage order fulfillment."

---

## PART 6: REFLECTION (1-2 minutes)

### 6A. Challenges Encountered
**[Speak naturally, from experience]**

"Throughout this project, I encountered several challenges:

**Technical Challenges:**

1. **Challenge**: [Example: Database migrations in production]
   - **Solution**: [What you did to solve it]
   - **Learning**: [What you learned]

2. **Challenge**: [Example: OAuth2 Google authentication]
   - **Solution**: [What you did to solve it]
   - **Learning**: [What you learned]

3. **Challenge**: [Example: Asset compilation and caching]
   - **Solution**: [What you did to solve it]
   - **Learning**: [What you learned]

4. **Challenge**: [Example: Environment variable management]
   - **Solution**: [What you did to solve it]
   - **Learning**: [What you learned]

---

### 6B. Deployment Problem-Solving
**[Share specific deployment lessons]**

"When deploying to production, the key was:

- Understanding the difference between local development and production environments
- Properly securing sensitive data (database credentials, secrets)
- Using environment variables for configuration
- Running database migrations before deploying code changes
- Monitoring logs to catch and debug issues quickly
- Testing thoroughly in a staging environment before production

One specific example: [Describe a deployment issue and how you solved it]"

---

### 6C. Key Lessons Learned
**[Reflect on the learning experience]**

"Working on Sweetoria taught me several important lessons:

1. **Production is Different**: What works locally can break in production. You need to test thoroughly and understand the hosting environment.

2. **Database Migrations Are Critical**: Versioning your database schema is as important as versioning your code. This allows your team to evolve the database safely.

3. **Security Matters**: Never commit secrets to git. Use environment variables. This protects your business and customer data.

4. **Documentation is Essential**: Clear code, database diagrams, and deployment notes make it easier for teams to maintain and scale applications.

5. **Monitoring and Logging**: Being able to see what's happening in production is crucial for troubleshooting and improving performance.

6. **Infrastructure as Code**: Using Docker and docker-compose makes development consistent and deployment reproducible.

---

### 6D. Conclusion
**[Speak with confidence and clarity]**

"In conclusion, Sweetoria demonstrates a complete, production-ready web application built with modern Symfony practices. 

The application includes:
- ✓ Professional architecture and code organization
- ✓ Secure authentication and authorization
- ✓ Complete CRUD functionality
- ✓ Admin and staff management interfaces
- ✓ Customer-facing e-commerce features
- ✓ Successful deployment to a live hosting environment

This project represents the skills required in professional web development: not just writing code, but deploying, securing, and maintaining applications in production.

Thank you for viewing. The application is live at [YOUR_URL], and all source code is available on GitHub at [YOUR_GITHUB_REPO].

[Optional: Pause and take questions if this is a presentation format]"

---

## TECHNICAL NOTES FOR DURING RECORDING

### Screen Setup
- Zoom in on terminal/IDE for readability (150-200%)
- Disable notifications
- Use a light, professional theme
- Close unnecessary browser tabs

### Recording Tips
- Speak clearly and at a moderate pace
- Pause between sections for effect
- Use descriptive language, not technical jargon (for non-technical viewers)
- Move cursor intentionally - don't jump around rapidly
- When showing code, read key lines aloud
- Don't scroll too fast through files

### Timing Guidelines
- Part 1 (Intro): 1-2 min
- Part 2 (Source Code): 3-4 min
- Part 3 (GitHub): 2-3 min  
- Part 4 (Deployment): 3-5 min
- Part 5 (Live Demo): 3-5 min
- Part 6 (Reflection): 1-2 min
- **Total: 13-22 minutes** ✓

### What to Have Ready Before Recording
- [ ] GitHub repository link accessible
- [ ] Hosting platform account logged in
- [ ] Live application URL ready
- [ ] Admin credentials (but never expose in video)
- [ ] Browser at 100% zoom initially, can increase as needed
- [ ] VS Code with the project open
- [ ] Screen recording software ready
- [ ] Quiet environment for audio

---

## SUBMISSION CHECKLIST

Before submitting, ensure you have:

- [ ] **Screen Recording**: 10-20 minute video covering all 6 parts
- [ ] **YouTube Link**: Video uploaded to YouTube (unlisted or public)
- [ ] **GitHub Repository**: Public repository with all source code
- [ ] **Live URL**: Hosted application is accessible
- [ ] **README**: GitHub repository includes a README explaining the project
- [ ] **Video Description**: YouTube video description includes links to:
  - Live application URL
  - GitHub repository URL
  - Brief project description

---

## FINAL TIPS

✨ **Make it Professional**: This is your portfolio piece. Take time to explain concepts clearly.

✨ **Tell a Story**: Don't just show code; explain why you made certain choices.

✨ **Be Confident**: You built this application. You should be proud of it.

✨ **Test Everything**: Do a test recording to check audio/video quality before final recording.

Good luck! 🎉

---

*Last Updated: May 2026*
*Project: Sweetoria - E-Commerce Bakery Platform*
