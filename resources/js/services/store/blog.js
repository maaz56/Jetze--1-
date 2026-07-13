// store/modules/blog.js
import apiService from "./apiService"; // adjust path if needed
import {
  FETCH_BLOGS,
  FETCH_BLOG,
  SAVE_BLOG,
  UPDATE_BLOG,
  DELETE_BLOG,
  PUBLISH_BLOG,
  SEND_BLOG_MAIL,
} from "./actions.type";

import {
  IS_LOADING,
  NOT_IS_LOADING,
  SET_BLOGS,
  SET_BLOG,
  SET_API_ERROR,
  CLEAR_API_ERRORS,
} from "./mutations.type";

import { toast } from "vue3-toastify";

const resolveApiErrorMessage = (error, fallback) => {
  const data = error?.response?.data;
  if (data?.errors && typeof data.errors === "object") {
    const firstField = Object.keys(data.errors)[0];
    const firstMessage = firstField ? data.errors[firstField]?.[0] : null;
    if (firstMessage) return firstMessage;
  }
  return data?.message || fallback;
};

const state = {
  blogs: [],
  currentBlog: null,
  isLoading: false,
  apiErrors: null,
};

const getters = {
  blogs: (state) => state.blogs,
  currentBlog: (state) => state.currentBlog,
  isLoading: (state) => state.isLoading,
  apiErrors: (state) => state.apiErrors,
};

const actions = {
  async [FETCH_BLOGS]({ commit }, params = {}) {
    commit(IS_LOADING);
    try {
      const response = await apiService.fetchBlogs(params);
      commit(SET_BLOGS, response.data);
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to load blogs");
      commit(SET_API_ERROR, error?.response?.data);
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [FETCH_BLOG]({ commit }, id) {
    commit(IS_LOADING);
    try {
      console.log(id);
      const response = await apiService.fetchBlog(id);
      console.log(response.data);
      commit(SET_BLOG, response.data);
      return response;
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to load blog");
      commit(SET_API_ERROR, error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [SAVE_BLOG]({ commit, dispatch }, params) {
    commit(IS_LOADING);
    try {
      const response = await apiService.saveBlog(params);

      toast.success("Blog post created successfully");
      // dispatch(FETCH_BLOGS); // refresh list
      return response.data; // useful if you want to redirect to edit
    } catch (error) {
      toast.error(resolveApiErrorMessage(error, "Failed to create blog"));
      commit(SET_API_ERROR, error?.response?.data?.errors || error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [UPDATE_BLOG]({ commit, dispatch }, params) {
    commit(IS_LOADING);
    try {
      await apiService.updateBlog(params);

      toast.success("Blog updated successfully");
      dispatch(FETCH_BLOGS);
    } catch (error) {
      toast.error(resolveApiErrorMessage(error, "Failed to update blog"));
      commit(SET_API_ERROR, error?.response?.data?.errors || error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [DELETE_BLOG]({ commit, dispatch }, id) {
    commit(IS_LOADING);
    try {
      await apiService.deleteBlog(id);
      toast.success("Blog deleted successfully");
      dispatch(FETCH_BLOGS);
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to delete blog");
      commit(SET_API_ERROR, error?.response?.data);
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [PUBLISH_BLOG]({ commit, dispatch }, { id, isPublished }) {
    commit(IS_LOADING);
    try {
      await apiService.patch(`/admin/blogs/${id}/publish`, { is_published: isPublished });
      toast.success(`Blog ${isPublished ? "published" : "unpublished"} successfully`);
      dispatch(FETCH_BLOGS);
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to change publish status");
      commit(SET_API_ERROR, error?.response?.data);
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [SEND_BLOG_MAIL]({ commit }, params) {
    commit(IS_LOADING);
    try {
      const response = await apiService.sendBlogMail(params);
      const queuedCount = response?.data?.queued_count || 0;
      toast.success(`Blog email queued for ${queuedCount} recipient${queuedCount === 1 ? "" : "s"}`);
      return response.data;
    } catch (error) {
      toast.error(resolveApiErrorMessage(error, "Failed to queue blog email"));
      commit(SET_API_ERROR, error?.response?.data?.errors || error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },
};

const mutations = {
  [IS_LOADING](state) {
    state.isLoading = true;
  },
  [NOT_IS_LOADING](state) {
    state.isLoading = false;
  },
  [SET_BLOGS](state, data) {
    state.blogs = data || data;
    state.isLoading = false;
  },
  [SET_BLOG](state, data) {
    state.currentBlog = data.data || data;
    state.isLoading = false;
  },
  [SET_API_ERROR](state, errors) {
    state.apiErrors = errors;
  },
  [CLEAR_API_ERRORS](state) {
    state.apiErrors = null;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
