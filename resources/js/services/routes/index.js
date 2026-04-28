import { adminRoutes } from "./admin.routes";
import { adminAccountsRoutes } from "./accounts.routes";
import { adminReservationRoutes } from "./reservation.routes";
import { agentRoutes } from "./agent.routes";
import { authRoutes } from "./auth.routes";
import { clientRoutes } from "./client.routes";
import { salesmanRoutes } from "./salesman.routes";
import { customerRoutes } from "./customer.routes";


const routes = [
    ...authRoutes,
    ...clientRoutes,
    ...agentRoutes,
    ...adminRoutes,
    ...salesmanRoutes,
    ...customerRoutes,
    // ...adminAccountsRoutes,
    ...adminReservationRoutes
     ];

export default routes;
