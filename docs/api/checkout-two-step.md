# 商城两步支付（前端对接）

网关可能将路径映射为 `/api/mall-agg/...`；应用内路由为 `/api/mall/...`。以 OpenAPI `docs/api.json` 为准。

## 顺序

1. `POST /api/mall/orders`，Body：`lines`（`product_id`, `quantity`）。成功得 `data.id`（`order_id`）、`total_price` 等。
2. `POST /api/mall/checkout`，Body：`order_id`（必填）、`points_minor`（可选，默认 0）。成功得 `data.prepay`（调起微信/支付宝等 SDK）、`data.order`（含 `checkout_phase`、`cash_payable_minor`、`points_deduct_minor` 等）。
3. 使用 `prepay` 唤起第三方支付；支付结果以渠道异步回调为准，客户端可 `GET /api/mall/orders/{id}` 确认 `status` 是否已为已支付。

## 鉴权

所有请求头：`X-User-Access-Token: <raw JWT>`（不要加 `Bearer`）。

## 金额

- `cash_payable_minor` = `total_price − points_minor`（服务端校验 `points_minor ≤ total_price`）。
- 未调用 checkout 前，`points_deduct_minor` / `cash_payable_minor` 为 `0`。

## 换新 prepay

订单仍为待支付且 `checkout_phase` 已为等待支付、且本次请求的 `points_minor` 与已落库一致时，可再次调用 checkout 以生成新的 `prepay`（视支付渠道能力而定）。

## 错误码（节选）

| HTTP | errorCode | 含义 |
|------|-----------|------|
| 401 | 40101 | 未登录 |
| 404 | 40401 | 订单不存在或不属于当前用户 |
| 422 | 100 | 参数校验失败 |
| 422 | 40001 | 业务拒绝（阶段不允许、积分不足等） |
