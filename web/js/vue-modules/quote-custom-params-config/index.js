import Vue from 'vue';
import App from './components/app/index.vue';
import store from './config/store/';
import VueRouter from 'vue-router';
import "regenerator-runtime/runtime";
import moment from 'moment';
import VueI18n from 'vue-i18n';
import messages from './localization';

import routes from './config/routing/routes';

Vue.use(VueRouter);
Vue.use(moment);
Vue.use(VueI18n);

const router = new VueRouter({
     routes,
     mode: 'abstract'
 });


const i18n = new VueI18n({
    locale: 'en', // set locale
    messages, // set locale messages
});

router.beforeEach((to, from, next) => {
     if (to.matched.some(record => record.meta.requiresAuth)) {
         store.commit('checkToken');
         if (store.getters.isLoggedIn) {
             console.log(store.getters.isLoggedIn);
             next()
             return
         } else {
             next({path: '/login'})
         }
     } else {
         console.log('no auth required');
         next()
     }
 });

const userWebApp =  new Vue({
    el: '#quote-custom-params-config',
    store,
    router,
    i18n,
    render: h => h(App) // h = createElement
});
router.replace('/');
