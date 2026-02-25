# static_site 部署说明

这是已转换好的纯静态站点（HTML/CSS/JS），可直接部署到 GitHub Pages。

## 部署步骤
1. 将 `static_site/` 目录下的全部文件上传到你的仓库根目录（确保根目录包含 `index.html` 与 `.nojekyll`）。
2. 打开 GitHub 仓库页面，进入 **Settings → Pages**。
3. 在 **Build and deployment** 中选择：
   - **Source**: Deploy from a branch
   - **Branch**: `main`（或你的默认分支）
   - **Folder**: `/ (root)`
4. 保存后等待 GitHub Pages 发布完成，访问页面给出的站点 URL。

## 本地预览
- 可直接双击 `index.html` 用浏览器打开。
- 或使用静态服务器预览：`python -m http.server 8000`，然后访问 `http://localhost:8000/`。

## 资源说明
- `static_site/skin/images` 使用符号链接指向仓库内已存在的 `template/pc/skin/images`，以避免重复提交二进制图片资源。
- 若你要把 `static_site/` 单独拷贝到其他仓库，请将该图片目录一并复制为真实文件夹。
