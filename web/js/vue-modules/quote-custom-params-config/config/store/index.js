import Vue from 'vue';
import Vuex from 'vuex';
//import * as getters from './getters.js';
//import * as mutations from './mutations.js';
import * as actions from './actions.js';
import * as modules from './modules';

Vue.use(Vuex);
export default  new Vuex.Store({
    state: {
        userId: []
    },
    modules:{
        ...modules
    },
    //mutations,
    //getters,
    actions
});