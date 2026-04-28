import apiService from "./apiService";

export async function fetchBlogSlugs() {
    const { data } = await apiService.get("/blog/slugs");
    return data;
}

export async function fetchBlogBySlug(slug) {
    const { data } = await apiService.get(`/blog/${slug}`);
    return data;
}
export async function getBlogRoutes() {
  const baseURL = process.env.VITE_API_ROOT || "http://localhost:8000";
  const { data } = await axios.get(`${baseURL}/api/blog/slugs`);
  return data.map(slug => `/blog/${slug}`);
}
