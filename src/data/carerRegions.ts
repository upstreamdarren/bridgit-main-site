export interface CarerRegion {
  area: string;
  organisation: string;
  href: string;
  logo?: string;
  x: number;
  y: number;
  comingSoon?: boolean;
}

export const carerRegions: CarerRegion[] = [
  { area: "Ayrshire (North)", organisation: "Unity Enterprise", href: "https://carers.bridgit.care/app/cgna/ca", logo: "/images/clients/unity-enterprise.png", x: 30, y: 16 },
  { area: "Ayrshire (South)", organisation: "Unity Enterprise", href: "https://carers.bridgit.care/app/cgsa/ca", logo: "/images/clients/south-ayrshire-council.jpg", x: 29, y: 22 },
  { area: "Bath & North East Somerset", organisation: "Banes Carers Centre", href: "https://carers.bridgit.care/app/banes/ca", logo: "/images/clients/banes-carers-centre.webp", x: 43, y: 74 },
  { area: "Bournemouth, Christchurch, Poole", organisation: "BCP Carer Support", href: "https://bridgit.care/support/bcp", logo: "/images/clients/bcp-council.png", x: 45, y: 84 },
  { area: "Brent", organisation: "Brent Carers Centre", href: "#area-help", logo: "/images/clients/brent-carers.png", x: 59, y: 70, comingSoon: true },
  { area: "Bridgend", organisation: "Bridgend Carers Centre", href: "https://carers.bridgit.care/app/bgcc/ca", logo: "/images/clients/bridgend-council.png", x: 34, y: 68 },
  { area: "Cambridgeshire", organisation: "Cambridgeshire Council", href: "https://bridgit.care/support/cambs", logo: "/images/clients/cambridgeshire-council.png", x: 63, y: 58 },
  { area: "Camden", organisation: "Camden Carers", href: "https://carers.bridgit.care/app/camc/ca", logo: "/images/clients/camden-carers.jpeg", x: 61, y: 72 },
  { area: "Coventry", organisation: "Coventry City Council", href: "https://bridgit.care/support/coventry", logo: "/images/clients/coventry-city-council.jpg", x: 50, y: 61 },
  { area: "Derbyshire", organisation: "Derbyshire Carers Association", href: "https://carers.bridgit.care/app/dca/ca", logo: "/images/clients/derbyshire-carers.png", x: 52, y: 49 },
  { area: "Devon", organisation: "Devon Carers", href: "https://carers.bridgit.care/devon/", logo: "/images/clients/devon-county-council.webp", x: 32, y: 88 },
  { area: "Dorset", organisation: "Dorset Council", href: "https://bridgit.care/support/dorset", logo: "/images/clients/dorset-council.png", x: 42, y: 86 },
  { area: "Dudley", organisation: "Dudley Metropolitan Borough Council", href: "https://carers.bridgit.care/app/dud/ca", logo: "/images/clients/dudley-council.png", x: 46, y: 61 },
  { area: "Durham", organisation: "Durham County Carers Support", href: "#area-help", logo: "/images/clients/durham-county-carers-support.png", x: 58, y: 31, comingSoon: true },
  { area: "Ealing", organisation: "Carers Trust Ealing", href: "https://carers.bridgit.care/app/ealing/ca", logo: "/images/clients/ealing-council.jpg", x: 58, y: 72 },
  { area: "Gloucestershire (South)", organisation: "South Gloucestershire Council", href: "https://carers.bridgit.care/app/sglo/ca", logo: "/images/clients/south-gloucestershire-council.png", x: 42, y: 70 },
  { area: "Hammersmith & Fulham", organisation: "Carers Network", href: "https://carers.bridgit.care/app/cnet/ca", logo: "/images/clients/carers-network.png", x: 60, y: 73 },
  { area: "Harrow", organisation: "Harrow Carers", href: "https://carers.bridgit.care/app/hcar/ca", x: 58, y: 69 },
  { area: "Hartlepool", organisation: "Hartlepool Carers", href: "https://carers.bridgit.care/app/hplc/ca", x: 61, y: 29 },
  { area: "Hillingdon", organisation: "Carers Trust Hillingdon", href: "https://carers.bridgit.care/app/cthil/ca", x: 57, y: 72 },
  { area: "Inverclyde", organisation: "Unity Enterprise", href: "https://carers.bridgit.care/app/cginv/ca", logo: "/images/clients/inverclyde-council.png", x: 32, y: 16 },
  { area: "Lincolnshire (North)", organisation: "North Lincolnshire Council", href: "https://carers.bridgit.care/app/cssnl/ca", logo: "/images/clients/north-lincolnshire-council.svg", x: 61, y: 45 },
  { area: "Lincolnshire (North East)", organisation: "North East Lincolnshire Council", href: "https://carers.bridgit.care/app/cssnel/ca", logo: "/images/clients/north-east-lincolnshire-council.svg", x: 65, y: 43 },
  { area: "Newcastle", organisation: "Newcastle Carers", href: "#area-help", x: 58, y: 27, comingSoon: true },
  { area: "Northamptonshire", organisation: "Northamptonshire Carers", href: "https://carers.bridgit.care/app/ncass/ca", logo: "/images/clients/north-northamptonshire-council.png", x: 57, y: 57 },
  { area: "Peterborough", organisation: "Peterborough City Council", href: "https://carers.bridgit.care/app/ptbc/ca", logo: "/images/clients/peterborough-council.png", x: 62, y: 53 },
  { area: "Plymouth", organisation: "Plymouth City Council", href: "https://carers.bridgit.care/devon/", logo: "/images/clients/plymouth-city-council.svg", x: 29, y: 92 },
  { area: "Sandwell", organisation: "Sandwell Metropolitan Borough Council", href: "https://carers.bridgit.care/app/sandwell/live", logo: "/images/clients/sandwell-council.webp", x: 47, y: 59 },
  { area: "Solihull", organisation: "Carers Trust", href: "https://carers.bridgit.care/app/ctsol/ca", logo: "/images/clients/solihull-council.png", x: 49, y: 62 },
  { area: "Somerset", organisation: "Thrive Somerset", href: "https://carers.bridgit.care/app/somerset/ca", logo: "/images/clients/thrive.png", x: 38, y: 78 },
  { area: "Torbay", organisation: "Torbay Council", href: "https://carers.bridgit.care/devon/", logo: "/images/clients/torbay-council.webp", x: 32, y: 90 },
  { area: "Warwickshire", organisation: "Warwickshire County Council", href: "https://bridgit.care/support/warwickshire", x: 51, y: 62 },
  { area: "Wolverhampton", organisation: "Wolverhampton City Council", href: "#area-help", x: 46, y: 58, comingSoon: true }
];
