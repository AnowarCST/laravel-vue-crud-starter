/**
 * Client-side role checks for showing/hiding UI only.
 * The API enforces authorization independently.
 */
export default class Gate {
    constructor(user) {
        this.user = user || {};
    }

    isAdmin() {
        return this.user.type === 'admin';
    }
}
