## ⚠️ Incohérences et Problèmes Détectés

### 1. **Terminologie Incohérente**

#### Problème : Mélange de termes pour désigner la même chose
- ❌ "don" / "donation" / "acte" utilisés de manière interchangeable
- ❌ "Dossier" / "Simulation" utilisés pour la même chose
- ❌ "Opportunités détectées" vs "Opportunités manquées" (concepts différents mais similaires)

**Recommandation** :
- Standardiser : **"Donation"** pour les actes enregistrés, **"Simulation"** pour les calculs prospectifs
- Utiliser **"Dossier"** uniquement dans le contexte notaire (dossier client)
- Clarifier : **"Opportunités détectées"** = nouvelles opportunités, **"Opportunités manquées"** = occasions passées

#### Problème : Titres de pages incohérents
- ❌ `home.html.twig` : "Lignée — Optimisez votre stratégie de transmission"
- ❌ `base.html.twig` (défaut) : "Optimisez votre patrimoine familial"
- ❌ `family/dashboard.html.twig` : "Tableau de bord de la famille"
- ❌ Navigation : "Ma Famille"

**Recommandation** :
- Harmoniser : "Ma Famille" partout (plus court, plus personnel)


### 2. **Messages Marketing Incohérents**

### 3. **Termes Techniques Ambigus**

#### Problème : "Rappel fiscal (-15 ans)"

#### Problème : "Optimisation disponible" vs "Potentiel d'exonération"


### 4. **Incohérences Navigation / Contenu**

#### Problème : Termes différents selon le contexte

### 5. **Problèmes de Formatage**

#### Problème : Markdown dans du HTML


#### Problème : Emoji dans les titres

### 6. **Incohérences de Casage**

#### Problème : Majuscules/minuscules incohérentes
- ❌ "Mon Profil" vs "Mon compte"
- ❌ "Nouveau Membre" vs "Nouvelle personne"
- ✅ Standardiser : **"Mon Compte"**, **"Nouveau Membre"**

### 7. **Messages d'État Incohérents**

#### Problème : Formulations différentes pour les états vides


---

## 📝 Recommandations Prioritaires

### 🔴 Priorité Haute

1. **Harmoniser la terminologie** :
   - "Donation" pour les actes
   - "Simulation" pour les calculs
   - "Dossier" uniquement pour notaires

3. **Standardiser les titres de pages** :
   - "Ma Famille" partout
   - "Mon Étude" pour notaires

### 🟡 Priorité Moyenne

4. **Clarifier les termes techniques** :
   - "Capacité de transmission disponible" → "Capacité de transmission"

5. **Harmoniser les messages d'état vides**

6. **Retirer les emojis** des titres

### 🟢 Priorité Basse

7. **Uniformiser la casse** (majuscules/minuscules)

8. **Harmoniser les messages marketing** (baseline unique)

---

## ✨ Suggestions d'Amélioration

### Messages Plus Clairs

### Cohérence Visuelle

- Utiliser les mêmes icônes pour les mêmes concepts partout
- Standardiser les couleurs (indigo pour actions principales, etc.)

### Accessibilité

- Ajouter des `alt` descriptifs aux images
- Vérifier les contrastes de couleurs
- S'assurer que tous les boutons ont des labels clairs

---
