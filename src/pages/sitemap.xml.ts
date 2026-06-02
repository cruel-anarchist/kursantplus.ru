const routes = [
  "/",
] as const;

export function GET({ site }: { site?: URL }) {
  const siteUrl = site ?? new URL("https://kursantplus.ru");
  const urls = routes
    .map((route) => {
      const url = new URL(route, siteUrl).toString();
      return `<url><loc>${url}</loc></url>`;
    })
    .join("");

  const body = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>`;

  return new Response(body, {
    headers: {
      "Content-Type": "application/xml; charset=utf-8",
    },
  });
}
