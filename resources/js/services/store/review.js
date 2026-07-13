import apiService from "./apiService";
import {
  SUBMIT_REVIEW,
  FETCH_REVIEWS_APPROVED,
  FETCH_REVIEWS,
  APPROVE_REVIEW,
  DELETE_REVIEW,
} from "./actions.type";

import {
  IS_LOADING,
  NOT_IS_LOADING,
  SET_REVIEWS,
  SET_APPROVED_REVIEWS,
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
  reviews: [],
  approvedReviews: [],
  isLoading: false,
  apiErrors: null,
};

const getters = {
  reviews: (state) => state.reviews,
  approvedReviews: (state) => state.approvedReviews,
  isLoading: (state) => state.isLoading,
  apiErrors: (state) => state.apiErrors,
};

const actions = {
  async [FETCH_REVIEWS]({ commit }, params = {}) {
    commit(IS_LOADING);
    try {
      const response = await apiService.fetchReviews(params);
      commit(SET_REVIEWS, response.data);
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to load reviews");
      commit(SET_API_ERROR, error?.response?.data);
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [FETCH_REVIEWS_APPROVED]({ commit }) {
    commit(IS_LOADING);
    try {
      const response = await apiService.fetchReviewsApproved();
      commit(SET_APPROVED_REVIEWS, response.data);
      return response.data;
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to load testimonials");
      commit(SET_API_ERROR, error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [SUBMIT_REVIEW]({ commit }, params) {
    commit(IS_LOADING);
    try {
      const response = await apiService.submitReview(params);
      toast.success(response.data?.message || "Thank you! Your review has been submitted for approval.");
      return response.data;
    } catch (error) {
      toast.error(resolveApiErrorMessage(error, "Failed to submit review"));
      commit(SET_API_ERROR, error?.response?.data?.errors || error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [APPROVE_REVIEW]({ commit, dispatch }, { id, is_approved }) {
    commit(IS_LOADING);
    try {
      const response = await apiService.approveReview({ id, is_approved });
      toast.success(response.data?.message || "Status updated successfully");
      return response.data;
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to update status");
      commit(SET_API_ERROR, error?.response?.data);
      throw error;
    } finally {
      commit(NOT_IS_LOADING);
    }
  },

  async [DELETE_REVIEW]({ commit }, id) {
    commit(IS_LOADING);
    try {
      const response = await apiService.deleteReview(id);
      toast.success(response.data?.message || "Review deleted successfully");
      return response.data;
    } catch (error) {
      toast.error(error?.response?.data?.message || "Failed to delete review");
      commit(SET_API_ERROR, error?.response?.data);
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
  [SET_REVIEWS](state, data) {
    state.reviews = data || [];
    state.isLoading = false;
  },
  [SET_APPROVED_REVIEWS](state, data) {
    state.approvedReviews = data || [];
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
