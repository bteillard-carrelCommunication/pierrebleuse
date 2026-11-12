/* Orchestres invités : la liste de la section Parcours et les points de la carte du monde.
 *
 * Une entrée = un orchestre (ou une salle) et la ville où le placer :
 *   nom       tel qu'il s'affiche, identique dans les deux langues ; vide : la ligne de la liste
 *             affiche la ville seule (chaque point de la carte a sa ligne, et réciproquement)
 *   ville     chaîne ou { fr, en } : l'étiquette du point
 *   lat, lon  degrés décimaux (nord et est positifs)
 * Ajouter une entrée ajoute la ligne et le point. Les entrées d'une même ville partagent un
 * point. Survoler une ligne allume son point ; survoler un point allume ses lignes. */
window.ORCHESTRES = [
  { nom: 'Royal Concertgebouw Orchestra', ville: 'Amsterdam', lat: 52.37, lon: 4.90 },
  { nom: 'London Philharmonic Orchestra', ville: { fr: 'Londres', en: 'London' }, lat: 51.51, lon: -0.13 },
  { nom: 'BBC Symphony Orchestra', ville: { fr: 'Londres', en: 'London' }, lat: 51.51, lon: -0.13 },
  { nom: 'City of Birmingham Symphony Orchestra', ville: 'Birmingham', lat: 52.48, lon: -1.90 },
  { nom: 'Orchestre de Paris', ville: 'Paris', lat: 48.86, lon: 2.35 },
  { nom: 'Orchestre national de France', ville: 'Paris', lat: 48.86, lon: 2.35 },
  { nom: 'NDR Elbphilharmonie Orchester', ville: { fr: 'Hambourg', en: 'Hamburg' }, lat: 53.55, lon: 9.99 },
  { nom: 'hr-Sinfonieorchester Frankfurt', ville: { fr: 'Francfort', en: 'Frankfurt' }, lat: 50.11, lon: 8.68 },
  { nom: 'SWR Symphonieorchester', ville: 'Stuttgart', lat: 48.78, lon: 9.18 },
  { nom: 'Royal Stockholm Philharmonic Orchestra', ville: 'Stockholm', lat: 59.33, lon: 18.07 },
  { nom: 'Swedish Radio Symphony Orchestra', ville: 'Stockholm', lat: 59.33, lon: 18.07 },
  { nom: 'Finnish Radio Symphony Orchestra', ville: 'Helsinki', lat: 60.17, lon: 24.94 },
  { nom: 'Orchestre de la Suisse Romande', ville: { fr: 'Genève', en: 'Geneva' }, lat: 46.20, lon: 6.14 },
  { nom: 'Tokyo Symphony Orchestra', ville: 'Tokyo', lat: 35.68, lon: 139.69 },
  { nom: 'Singapore Symphony Orchestra', ville: { fr: 'Singapour', en: 'Singapore' }, lat: 1.35, lon: 103.82 },
  { nom: 'Orquestra Sinfônica do Estado de São Paulo', ville: 'São Paulo', lat: -23.55, lon: -46.63 },
  { nom: 'Auckland Philharmonia', ville: 'Auckland', lat: -36.85, lon: 174.76 },
  /* ajoutée le 12/11/2026 : la ville seule dans la liste, le nom de l'orchestre reste à fournir */
  { nom: '', ville: 'Salt Lake City', lat: 40.76, lon: -111.89 }
];
