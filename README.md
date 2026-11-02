# blessing-github-oauth

用 GitHub 账号登录 Blessing Skin。

玩家在登录页点击「GitHub 登录」，授权后自动创建或进入皮肤站账号，不需要另外填写密码。

## 安装

1. 把 `blessing-github-oauth` 目录放到皮肤站的 `plugins/` 下。
2. 进入后台，在「插件」里启用 **blessing-github-oauth**。
3. 打开插件配置页，填写 GitHub OAuth App 的 Client ID、Client Secret。
4. 在 GitHub OAuth App 里把回调地址设置为：

```
https://你的皮肤站域名/github-oauth/callback
```

5. 清缓存：

```bash
php artisan cache:clear
```

## 更新

覆盖插件目录后执行：

```bash
php artisan view:clear
php artisan cache:clear
```

## 依赖

- Blessing Skin Server
- PHP `^8.1`

## 许可

MIT

## 依赖

- Blessing Skin 6+
- 必须：[blessing-oauth-core](../blessing-oauth-core)（提供虚拟邮箱后缀设置；本插件直接依赖它，未安装或未启用时无法启用）


