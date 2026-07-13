import apiService from "@/config/axios";

export async function fetchBlogSlugs() {
    const { data } = await apiService.get("/blog/slugs");
    return data;
}

export async function fetchBlogBySlug(slug) {
    const { data } = await apiService.get(`/blog/${slug}`);
    return data;
}
export async function getBlogRoutes() {
  const { data } = await apiService.get("/blog/slugs");
  return data.map(slug => `/blog/${slug}`);
}
