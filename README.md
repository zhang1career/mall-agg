# Mall aggregation

商城聚合中间件（Laravel）。

## 环境与配置

与 **user-agg** 相同：**基础 `.env` + 按 `APP_ENV` 叠加 `.env.{dev|test|prod}`**。

实现位置：`bootstrap/app.php` 中 `afterLoadingEnvironment` 调用 `Paganini\Env\LayeredEnvLoader::loadEnvironmentOverlay`。

### 引导步骤

1. 复制示例：`cp .env.example .env`，按需填写（如 `APP_KEY`、数据库、`API_GATEWAY_BASE_URL` 等）。
2. **叠加文件（可选）**：若部署环境使用 `APP_ENV=prod`（或 `dev` / `test`），可在项目根目录增加 `.env.prod`（或 `.env.dev` / `.env.test`），其中**仅写需要覆盖的键**；启动后这些值会覆盖 `.env` 里同名变量。
3. `APP_ENV` 为 `local`、`testing`（如 PHPUnit）等不在 `dev|test|prod` 时，**不会**加载叠加文件，避免与本地/测试配置冲突。

### 开发与运行

```bash
composer install
php artisan key:generate
php artisan serve
```

详见 `.env.example` 顶部说明。
