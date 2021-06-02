import { generalconfig } from '../../components/generalconfig/';
import { dropdowndetails } from '../../components/dropdowndetails/';

const routes = [
    {
        path: '/',
        name: 'index',
        component: generalconfig,
        meta: { requiresAuth: false }
    },
    {
        path: 'dropdowndetails/:id',
        component: dropdowndetails,
        name: 'dropdowndetails',
        meta: { requiresAuth: false }
    }
];

export default routes;
