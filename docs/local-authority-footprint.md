# Local-authority footprint

The public site reports a current footprint of **60 unique UK local-authority areas**.

## Calculation

- Established Bridgit footprint before adding My UK Life: 40 areas.
- My UK Life West Midlands programme: 14 areas.
- My UK Life South East programme: 18 areas.
- Areas already represented in the established footprint: 12.
- Unique total: `40 + 14 + 18 - 12 = 60`.

The 12 overlaps are Birmingham, Coventry, Dudley, Herefordshire, Sandwell, Solihull, Staffordshire, Telford and Wrekin, Warwickshire, Wolverhampton, Worcestershire and Milton Keynes.

## Evidence

- WMADASS describes its branch as the 14 West Midlands councils and lists each council: https://www.wm-adass.org.uk/about/wm-adass-branch-and-directors-of-adult-social-services/
- SESCA describes South East ADASS as a partnership of the 18 South East councils with adult social care responsibilities: https://sesca.org.uk/
- Existing Bridgit partner and service-area records are held in `src/data/partners.json`, `src/data/carerRegions.ts` and `src/data/youngCarerRegions.ts`.

Recheck this calculation whenever a regional programme or direct local-authority service is added or removed.
