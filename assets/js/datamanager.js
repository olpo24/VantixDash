/**
 * assets/js/datamanager.js
 */
const DataManager = {
    
    async fetchSites() {
        try {
            const response = await fetch('data.php');
            if (!response.ok) throw new Error('Fehler beim Laden');
            return await response.json();
        } catch (error) {
            throw error;
        }
    },

    async addSite(url, name) {
        try {
            const response = await fetch('data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: url, name: name })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Server-Fehler');
            return result;
        } catch (error) {
            throw error;
        }
    },

    // NEU: Die Lösch-Funktion innerhalb des Objekts
    async deleteSite(url) {
        try {
            const response = await fetch('data.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: url })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Lösch-Fehler');
            return result;
        } catch (error) {
            throw error;
        }
    }
};
