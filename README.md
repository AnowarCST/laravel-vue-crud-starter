
# Laravel+Vue Crud Starter


[![Build Status](https://dev.azure.com/anowarhossain/laravel-vue-crud-starter/_apis/build/status/AnowarCST.laravel-vue-crud-starter?branchName=master)](https://dev.azure.com/anowarhossain/laravel-vue-crud-starter/_build/latest?definitionId=6&branchName=master)


## About Repository

A very simple Laravel 8 + Vue 2 + AdminLTE 3 based Curd Starter template for SPA Application.
<p align="center">
<img src="https://i.imgur.com/mZAHbUL.png">
<img src="https://i.imgur.com/3hhoQnq.png">
<img src="https://i.imgur.com/aHtQkYl.png">
<img src="https://i.imgur.com/V7OuwLn.png">
</p>

## Tech Specification

- Laravel 8
- Vue 2 + VueRouter + vue-progressbar + sweetalert2 + laravel-vue-pagination
- Laravel Passport
- Admin LTE 3 + Bootstrap 4 + Font Awesome 5
- PHPUnit Test Case/Test Coverage

## Features

- Modal based Create+Edit, List with Pagination, Delete with Sweetalert
- Login, Register, Forget+Reset Password as default auth
- Profile, Update Profile, Change Password, Avatar
- Product Management 
- User Management
- Settings: Categories, Tags
- Frontend and Backend User ACL with Gate Policy (type: admin/user)
- Simple Static Dashboard
- Developer Options for OAuth Clients and Personal Access Token
- Build with Docker

## Installation

- `git clone https://github.com/AnowarCST/laravel-vue-crud-starter.git`
- `cd laravel-vue-crud-starter/`
- `composer install`
- `cp .env.example .env`
- Update `.env` and set your database credentials
- `php artisan key:generate`
- `php artisan migrate`
- `php artisan db:seed`
- `php artisan passport:install`
- `npm install`
- `npm run dev`
- `php artisan serve`

## Install with Docker

- `cp .env.example .env`
- Update `.env` if needed; defaults expect the compose stack (`DB_HOST=db`, `DB_USERNAME=laravel`, `DB_PASSWORD=secret`).
- `docker compose up -d`
- `docker compose exec app composer install`
- `docker compose exec app php artisan key:generate`
- `docker compose exec app php artisan migrate`
- `docker compose exec app php artisan db:seed`
- `docker compose exec app php artisan passport:install`
- Application http://localhost:8000/
- Vite dev server http://localhost:5173/
- MySQL available on port 3306 (user `laravel`, password `secret`)


## Unit Test

#### run PHPUnit

```bash
# run PHPUnit all test cases
vendor/bin/phpunit
# or Feature test only
vendor/bin/phpunit --testsuite Feature
```

#### Code Coverage Report

```bash
# reports is a directory name
vendor/bin/phpunit --coverage-html reports/
```
A `reports` directory has been created for code coverage report. Open the dashboard.html.


## Credit
This repository is motivated by [Hujjat/laravStart](https://github.com/Hujjat/laravStart) and his awesome video tutorial in [Youtube](https://www.youtube.com/playlist?list=PLB4AdipoHpxaHDLIaMdtro1eXnQtl_UvE).

## License

[MIT license](https://opensource.org/licenses/MIT).

---

## Contributing & Community

- **Checklist:** see [`CHECKLIST.md`](./CHECKLIST.md)
- **Project Rules (AI-friendly):** see [`RULES.md`](./RULES.md)
- **Code of Conduct:** see [`CODE_OF_CONDUCT.md`](./CODE_OF_CONDUCT.md)
- **Tasks:** see [`TASKS.md`](./TASKS.md)

By contributing, you agree to follow the rules and the Code of Conduct. If you use AI tooling, please review outputs carefully and take responsibility for the results.
