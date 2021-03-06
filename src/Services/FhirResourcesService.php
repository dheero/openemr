<?php
/**
 * FHIRResources service class
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2018 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


namespace OpenEMR\Services;

use OpenEMR\FHIR\R4\FHIRDomainResource\FHIREncounter;
use OpenEMR\FHIR\R4\FHIRDomainResource\FHIRPatient;
use OpenEMR\FHIR\R4\FHIRDomainResource\FHIRPractitioner;
use OpenEMR\FHIR\R4\FHIRElement\FHIRAddress;
use OpenEMR\FHIR\R4\FHIRElement\FHIRAdministrativeGender;
use OpenEMR\FHIR\R4\FHIRElement\FHIRHumanName;
use OpenEMR\FHIR\R4\FHIRElement\FHIRId;
use OpenEMR\FHIR\R4\FHIRElement\FHIRReference;
use OpenEMR\FHIR\R4\FHIRResource\FHIREncounter\FHIREncounterParticipant;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle\FHIRBundleLink;
use OpenEMR\FHIR\R4\PHPFHIRResponseParser;

use OpenEMR\Services\ProviderService;
use OpenEMR\Services\FhirValidationService;

//use OpenEMR\FHIR\R4\FHIRResource\FHIREncounter\FHIREncounterLocation;
//use OpenEMR\FHIR\R4\FHIRResource\FHIREncounter\FHIREncounterDiagnosis;
//use OpenEMR\FHIR\R4\FHIRElement\FHIRPeriod;
//use OpenEMR\FHIR\R4\FHIRElement\FHIRParticipantRequired;

class FhirResourcesService
{
    public function createBundle($resource = '', $resource_array = [], $encode = true)
    {
        $bundleUrl = \RestConfig::$REST_FULL_URL;
        $nowDate = date("Y-m-d\TH:i:s");
        $meta = array('lastUpdated' => $nowDate);
        $bundleLink = new FHIRBundleLink(array('relation' => 'self', 'url' => $bundleUrl));
        // set bundle type default to collection so may include different
        // resource types. at least I hope thats how it works....
        $bundleInit = array(
            'identifier' => $resource . "bundle",
            'type' => 'collection',
            'total' => count($resource_array),
            'meta' => $meta);
        $bundle = new FHIRBundle($bundleInit);
        $bundle->addLink($bundleLink);
        foreach ($resource_array as $addResource) {
            $bundle->addEntry($addResource);
        }

        if ($encode) {
            return json_encode($bundle);
        }

        return $bundle;
    }

    public function createPatientResource($resourceId = '', $data = '', $encode = true)
    {
        // @todo add display text after meta
        $nowDate = date("Y-m-d\TH:i:s");
        $id = new FhirId();
        $id->setValue($resourceId);
        $name = new FHIRHumanName();
        $address = new FHIRAddress();
        $gender = new FHIRAdministrativeGender();
        $meta = array('versionId' => '1', 'lastUpdated' => $nowDate);
        $initResource = array('id' => $id, 'meta' => $meta);
        $name->setUse('official');
        $name->setFamily($data['lname']);
        $name->given = [$data['fname'], $data['mname']];
        $address->addLine($data['street']);
        $address->setCity($data['city']);
        $address->setState($data['state']);
        $address->setPostalCode($data['postal_code']);
        $gender->setValue(strtolower($data['sex']));

        $patientResource = new FHIRPatient($initResource);
        //$patientResource->setId($id);
        $patientResource->setActive(true);
        $patientResource->setGender($gender);
        $patientResource->addName($name);
        $patientResource->addAddress($address);

        if ($encode) {
            return json_encode($patientResource);
        } else {
            return $patientResource;
        }
    }

    public function createPractitionerResource($provider_id = '', $encode = true)
    {
        if (!$provider_id) {
            return false;
        }
        $this->providerService = new ProviderService();
        $data = $this->providerService->getById($provider_id);
        $resource = new FHIRPractitioner();
        $id = new FhirId();
        $name = new FHIRHumanName();
        $address = new FHIRAddress();
        $id->setValue('' . $provider_id);
        $name->setUse('official');
        $name->setFamily($data['lname']);
        $name->given = [$data['fname'], $data['mname']];
        $address->addLine($data['street']);
        $address->setCity($data['city']);
        $address->setState($data['state']);
        $address->setPostalCode($data['zip']);
        $resource->setId($id);
        $resource->setActive(true);
        $gender = new FHIRAdministrativeGender();
        $gender->setValue('unknown');
        $resource->setGender($gender);
        $resource->addName($name);
        $resource->addAddress($address);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    public function createEncounterResource($eid = '', $data = '', $encode = true)
    {
        $pid = $data['pid'];
        //$temp = $data['provider_id'];
        //$r = $this->createPractitionerResource($data['provider_id'], $temp);
        $resource = new FHIREncounter();
        $id = new FhirId();
        $id->setValue($eid);
        $resource->setId($id);
        $participant = new FHIREncounterParticipant();
        $prtref = new FHIRReference;
        $temp = 'Practitioner/' . $data['provider_id'];
        $prtref->setReference($temp);
        $participant->setIndividual($prtref);
        $date = date('Y-m-d', strtotime($data['date']));
        $participant->setPeriod(['start' => $date]);

        $resource->addParticipant($participant);
        $reason = new FHIRCodeableConcept();
        $reason->setText($data['reason']);
        $resource->addReasonCode($reason);
        $resource->status = 'finished';
        $resource->setSubject(['reference' => "Patient/$pid"]);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    public function parsePatientResource($fhirJson)
    {
        $data["title"] = "";
        $name = [];
        foreach ($fhirJson["name"] as $sub_name) {
            if ($sub_name["use"] == "official") {
                $name = $sub_name;
                break;
            }
        }
        $data["lname"] = $name["family"];
        $data["fname"] = $name["given"][0];
        $data["mname"] = $name["given"][1];
        $data["street"] = $fhirJson["address"][0]["line"][0];
        $data["postal_code"] = $fhirJson["address"][0]["postalCode"];
        $data["city"] = $fhirJson["address"][0]["city"];
        $data["state"] = $fhirJson["address"][0]["state"];
        $data["country_code"] = "" ;
        $phone = [];
        foreach ($fhirJson["telecom"] as $phone) {
            if ($phone["use"] == "mobile") {
                $name = $phone;
                break;
            }
        }
        $data["phone_contact"] = $phone["value"];
        $data["DOB"] = $fhirJson["birthDate"];
        $data["sex"] = $fhirJson["gender"];
        $data["race"] = "";
        $data["ethnicity"] = "";
        return $data;
    }

    public function parseResource($rjson = '', $scheme = 'json')
    {
        $parser = new PHPFHIRResponseParser(false);
        if ($scheme == 'json') {
            $class_object = $parser->parse($rjson);
        } else {
            // @todo xml- not sure yet.
        }
        return $class_object; // feed to resource class or use as is object
    }
}
