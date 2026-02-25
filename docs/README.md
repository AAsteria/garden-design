# static_site 静态站点说明

## 素材映射表（assets/img → 页面/模块）

| 素材文件 | 使用页面 | 模块 |
|---|---|---|
| `assets/img/网站封面.jpg` | `index.html` / `product.html` / `news.html` / `about.html` / `article.html` / `product_detail.html` | 首页轮播、设计理念、文创示意图、内页轮播 |
| `assets/img/网站封面2.jpg` | `index.html` / `product.html` / `news.html` / `about.html` / `article.html` / `product_detail.html` | Logo、首页轮播、集章本示意、内页轮播 |
| `assets/img/神泉峡人物IP.png` | `index.html` / `product.html` / `about.html` / `product_detail.html` | 关于我们首屏图、IP设计理念、产品详情图 |
| `assets/img/购物袋第一款.png` | `index.html` / `product.html` / `product_detail.html` | 购物袋设计理念、产品详情图 |
| `assets/img/购物袋第二款.png` | `product.html` | 购物袋设计理念（第二款） |
| `assets/img/印章.jpg` | `index.html` / `product.html` / `product_detail.html` | 印章模块、文创购买、产品详情图 |
| `assets/img/民宿宣传图.png` | `index.html` | 了解更多 → 门票预定 |
| `assets/img/情景剧宣传图.png` | `index.html` | 了解更多 → 门票预定 |
| `assets/img/讲解.png` | `index.html` | 了解更多 → 门票预定 |
| `assets/img/地图(1).png` | `about.html` | 景区地图模块 |
| `assets/img/新闻动态资料/第一条.jpg` ~ `第五条.jpg` | `index.html` / `article.html` | 更多精彩（5图示例）、新闻详情配图 |

> 说明：本次仅通过 HTML 引用替换素材，未移动或删除 `assets/img/` 目录中的任何文件。

## 本地预览

```bash
cd static_site && python3 -m http.server 8080
```

浏览器访问：`http://127.0.0.1:8080/index.html`

## GitHub Pages 发布

1. 将 `static_site/` 目录内文件发布到仓库 Pages 对应分支目录（常见为根目录）。
2. 在 GitHub 仓库中进入 **Settings → Pages**。
3. Source 选择 **Deploy from a branch**。
4. Branch 选择发布分支（例如 `main`）及目录（通常 `/root`）。
5. 保存后等待构建完成，访问 Pages URL 验证页面。
