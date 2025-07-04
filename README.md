# typecho-cloudflare-r2
upload img to cloudflare r2，上传图片到`Cloudflare R2`

# 安装

复制`CloudflareR2`到`usr/plugins`目录下

在后台`控制台-插件`中启用 `CloudflareR2`

# 配置

1. 在`Cloudflare`创建存储，桶名称填入插件配置中的`存储桶`
2. 在`Cloudflare`创建API 令牌，权限选择`对象读和写`，指定存储桶选择刚刚创建的存储桶，提交后填入页面上显示的`访问密钥ID`、`机密访问密钥`、`Endpoint`
3. 在`Cloudflare`的`桶设置-自定义域`，填写要使用的使命，完成填入插件配置的`公开访问域名`

# 使用

正常上传图片即可，插件会自动将图片上传到`Cloudflare R2`，并返回公开访问的图片URL，不影响已经上传的本地文件。
