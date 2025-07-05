# typecho-cloudflare-r2
上传文件附件到`Cloudflare R2`

# 安装

将`CloudflareR2`复制到`usr/plugins/`目录下

在后台`控制台-插件`中启用 `Cloudflare R2`

# 配置

1. 在`Cloudflare R2`创建存储，桶名称填入插件配置中的`存储桶`
2. 在`Cloudflare R2`创建API 令牌，权限选择`对象读和写`，指定`存储桶`选择刚刚创建的存储桶，提交后在插件配置中填入页面上显示的`访问密钥ID`、`机密访问密钥`、`Endpoint`
3. 在`Cloudflare R2`的`桶设置-自定义域`，填写要使用的域名，完成填入插件配置中的`公开访问域名`

# 使用

正常上传文件附件即可，插件会自动将文件上传到`Cloudflare R2`，并返回公开访问的文件URL，不影响已经上传的本地文件。
