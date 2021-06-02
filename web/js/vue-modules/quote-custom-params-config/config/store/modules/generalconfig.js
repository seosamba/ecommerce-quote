import merge from 'lodash/merge';

let defaultState = {
    configDataInfo: [],
    configScreenInfo: []
};

let state = {};
merge(state, defaultState);

const actions = {

};

const mutations = {
    setConfigDataInfo : (state, payload) => {
        state.configDataInfo = payload;
    },
    setConfigScreenInfo : (state, payload) => {
        state.configScreenInfo = payload;
    }
}

const getters = {
    getConfigDataInfo : (state) => {
        return state.configDataInfo
    },
    getConfigScreenInfo : (state) => {
        return state.configScreenInfo
    }
};
export default {
    state,
    actions,
    getters,
    mutations
};