/* tslint:disable */
import { Injectable } from '@angular/core';

/**
 * Contains global configuration for API services
 */
@Injectable()
export class ApiConfiguration {
  rootUrl: string = "/api/v1";
  // rootUrl: string = "http://localhost:10010/api/v1";
  // rootUrl: string = "http://cryobeta-api.dfm-europe.com/api/v1";
}
